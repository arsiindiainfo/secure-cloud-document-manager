/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useQuery } from '@tanstack/react-query';
import { fetchDocument } from '../documents/api';
import { fetchFolder } from '../browser/api';
import { ShareDialog } from './ShareDialog';
import type { ShareTarget } from './api';
import type { DocumentDetail, FolderDetail } from '../../types/api';

interface ShareDialogLoaderProps {
  target: ShareTarget;
  onClose: () => void;
}

function fetchTarget(target: ShareTarget): Promise<DocumentDetail | FolderDetail> {
  return target.type === 'document' ? fetchDocument(target.id) : fetchFolder(target.id);
}

/**
 * A listing row's "Share" button needs the target's effectivePermission
 * (ShareDialog gates its sections on it) but nothing else — this fetches
 * just that and renders only the dialog, instead of also mounting the full
 * detail slide-over behind it.
 */
export function ShareDialogLoader({ target, onClose }: ShareDialogLoaderProps) {
  const { data } = useQuery({
    queryKey: target.type === 'document' ? ['document', target.id] : ['folder', target.id],
    queryFn: () => fetchTarget(target),
  });

  if (!data) return null;

  return <ShareDialog target={target} effectivePermission={data.effectivePermission} onClose={onClose} />;
}
