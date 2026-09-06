/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useEffect } from 'react';
import { useDocumentUpload } from './useDocumentUpload';
import { formatBytes, ALLOWED_EXTENSIONS_LABEL } from '../../lib/fileTypes';

interface UploadDialogProps {
  folderId: number;
  initialFiles: File[];
  onClose: () => void;
}

/** §22.6 — multi-file drop zone with per-file progress; one file's error never blocks the rest. */
export function UploadDialog({ folderId, initialFiles, onClose }: UploadDialogProps) {
  const { items, uploadFiles } = useDocumentUpload(folderId);

  useEffect(() => {
    void uploadFiles(initialFiles);
    // Only run once per mount — initialFiles is a snapshot of what was dropped/selected.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const allSettled = items.length > 0 && items.every((item) => item.status === 'done' || item.status === 'error');

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div role="dialog" aria-modal="true" className="w-full max-w-md rounded-lg bg-white p-6 shadow-lg dark:bg-slate-800">
        <h2 className="mb-1 text-base font-semibold text-slate-900 dark:text-slate-100">Uploading files</h2>
        <p className="mb-4 text-xs text-slate-500 dark:text-slate-400">Allowed: {ALLOWED_EXTENSIONS_LABEL}</p>

        <ul className="mb-5 max-h-72 space-y-3 overflow-y-auto">
          {items.map((item) => (
            <li key={item.key} className="text-sm">
              <div className="flex items-center justify-between">
                <span className="truncate text-slate-800 dark:text-slate-200" title={item.file.name}>
                  {item.file.name}
                </span>
                <span className="ml-2 shrink-0 text-xs text-slate-500">{formatBytes(item.file.size)}</span>
              </div>

              {item.status === 'error' ? (
                <p className="mt-1 text-xs text-red-600">{item.error}</p>
              ) : (
                <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                  <div
                    className={`h-full rounded-full transition-all ${item.status === 'done' ? 'bg-green-500' : 'bg-blue-500'}`}
                    style={{ width: `${item.status === 'done' ? 100 : item.progress}%` }}
                  />
                </div>
              )}
              {item.status === 'processing' && <p className="mt-1 text-xs text-slate-500">Finalizing…</p>}
            </li>
          ))}
        </ul>

        <div className="flex justify-end">
          <button
            onClick={onClose}
            disabled={!allSettled}
            className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
          >
            {allSettled ? 'Done' : 'Uploading…'}
          </button>
        </div>
      </div>
    </div>
  );
}

