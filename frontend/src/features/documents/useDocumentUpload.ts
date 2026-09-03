/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import * as api from './api';
import { folderChildrenKey } from '../browser/hooks';
import { ALLOWED_MIME_TYPES, MAX_UPLOAD_SIZE_BYTES } from '../../lib/fileTypes';
import { uploadWithProgress, sha256Hex } from '../../lib/uploadWithProgress';
import { apiErrorMessage } from '../../lib/apiError';

export type UploadStatus = 'pending' | 'uploading' | 'processing' | 'done' | 'error';

export interface UploadItem {
  key: string;
  file: File;
  progress: number;
  status: UploadStatus;
  error?: string;
}

/**
 * Owns the three-step direct-to-S3 upload sequence end to end (§21.2):
 * initiate -> PUT the bytes straight to S3 with progress -> complete. The
 * API server never sees the file bytes.
 */
export function useDocumentUpload(folderId: number) {
  const queryClient = useQueryClient();
  const [items, setItems] = useState<Record<string, UploadItem>>({});

  function patch(key: string, changes: Partial<UploadItem>) {
    setItems((prev) => ({ ...prev, [key]: { ...prev[key], ...changes } }));
  }

  async function uploadOne(file: File): Promise<void> {
    const key = `${file.name}-${file.size}-${file.lastModified}`;
    setItems((prev) => ({ ...prev, [key]: { key, file, progress: 0, status: 'pending' } }));

    if (!ALLOWED_MIME_TYPES.includes(file.type)) {
      patch(key, { status: 'error', error: 'File type not supported' });
      return;
    }
    if (file.size > MAX_UPLOAD_SIZE_BYTES) {
      patch(key, { status: 'error', error: 'File too large (max 25MB)' });
      return;
    }

    try {
      const { uploadUrl, s3Key } = await api.initiateUpload(folderId, file.name, file.type, file.size);

      patch(key, { status: 'uploading' });
      await uploadWithProgress(uploadUrl, file, file.type, (pct) => patch(key, { progress: pct }));

      patch(key, { status: 'processing', progress: 100 });
      const checksum = await sha256Hex(file);
      await api.completeUpload(folderId, s3Key, file.name, checksum);

      patch(key, { status: 'done' });
      void queryClient.invalidateQueries({ queryKey: folderChildrenKey(folderId) });
    } catch (err) {
      patch(key, { status: 'error', error: apiErrorMessage(err, 'Upload failed') });
    }
  }

  async function uploadFiles(files: File[]): Promise<void> {
    // Independent per-file failures shouldn't block the rest of the batch (§22.6).
    await Promise.allSettled(files.map(uploadOne));
  }

  function reset() {
    setItems({});
  }

  return { items: Object.values(items), uploadFiles, reset };
}

