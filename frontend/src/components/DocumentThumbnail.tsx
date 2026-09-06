/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useQuery } from '@tanstack/react-query';
import { fetchThumbnailUrl } from '../features/documents/api';
import { FileTypeIcon } from './FileTypeIcon';

interface DocumentThumbnailProps {
  documentId: number;
  hasThumbnail: boolean;
  mimeType: string | null;
}

/**
 * Listing-row thumbnail (§22.3) — lazily fetches a presigned URL only for
 * rows that actually have one ready, falling back to a per-type icon
 * immediately for everything else instead of showing a loading spinner.
 */
export function DocumentThumbnail({ documentId, hasThumbnail, mimeType }: DocumentThumbnailProps) {
  const { data, isError } = useQuery({
    queryKey: ['thumbnail', documentId],
    queryFn: () => fetchThumbnailUrl(documentId),
    enabled: hasThumbnail,
    staleTime: 4 * 60 * 1000, // presigned URLs are short-lived (§10) — refetch well before they'd expire
  });

  if (hasThumbnail && data && !isError) {
    return <img src={data.url} alt="" className="h-8 w-8 shrink-0 rounded object-cover" />;
  }

  return <FileTypeIcon mimeType={mimeType} />;
}
