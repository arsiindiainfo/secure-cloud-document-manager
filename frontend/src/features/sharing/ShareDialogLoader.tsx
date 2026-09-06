/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useQuery } from '@tanstack/react-query';
import { fetchDocument } from '../documents/api';
import { ShareDialog } from './ShareDialog';

interface ShareDialogLoaderProps {
  documentId: number;
  onClose: () => void;
}

/**
 * A listing row's "Share" button needs the document's effectivePermission
 * (ShareDialog gates its sections on it) but nothing else — this fetches
 * just that and renders only the dialog, instead of also mounting the full
 * DocumentDetailPanel slide-over behind it.
 */
export function ShareDialogLoader({ documentId, onClose }: ShareDialogLoaderProps) {
  const { data } = useQuery({
    queryKey: ['document', documentId],
    queryFn: () => fetchDocument(documentId),
  });

  if (!data) return null;

  return <ShareDialog documentId={documentId} effectivePermission={data.effectivePermission} onClose={onClose} />;
}
