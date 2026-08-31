import { useQuery } from '@tanstack/react-query';
import { fetchPreviewUrl } from '../features/documents/api';
import type { ProcessingStatus } from '../types/api';

interface FilePreviewProps {
  documentId: number;
  mimeType: string;
  processingStatus: ProcessingStatus | null;
}

const INLINE_PREVIEWABLE = ['application/pdf', 'image/png', 'image/jpeg'];

/**
 * §22.4 — inline for PDF/image; everything else (DOCX/XLSX/ZIP, or a type
 * whose thumbnail isn't ready) falls back to a "download to view" notice.
 * The backend, not this component, decides what's inline-able (§18) — this
 * just renders whatever presigned URL it gets back.
 *
 * §9.3/§22.4 — a non-inlineable type has no preview until the Lambda's
 * callback lands, so this shows "generating preview…" instead of querying
 * (and getting a THUMBNAIL_NOT_READY 404) while processing is still pending.
 */
export function FilePreview({ documentId, mimeType, processingStatus }: FilePreviewProps) {
  const needsThumbnail = !INLINE_PREVIEWABLE.includes(mimeType);
  const stillProcessing = needsThumbnail && (processingStatus === 'PENDING' || processingStatus === 'PROCESSING');

  const { data, isLoading, isError } = useQuery({
    queryKey: ['document-preview', documentId],
    queryFn: () => fetchPreviewUrl(documentId),
    enabled: !stillProcessing,
  });

  if (stillProcessing) {
    return (
      <div className="flex h-64 flex-col items-center justify-center gap-2 rounded-md bg-slate-50 text-sm text-slate-500 dark:bg-slate-900 dark:text-slate-400">
        <div className="h-8 w-8 animate-pulse rounded-full bg-slate-300 dark:bg-slate-600" />
        <p>Generating preview…</p>
      </div>
    );
  }

  if (isLoading) {
    return <div className="h-64 animate-pulse rounded-md bg-slate-200 dark:bg-slate-700" />;
  }

  if (isError || !data) {
    return (
      <div className="flex h-64 flex-col items-center justify-center gap-2 rounded-md bg-slate-50 text-sm text-slate-500 dark:bg-slate-900 dark:text-slate-400">
        <p>No preview available for this file.</p>
        <p>Download it to view the contents.</p>
      </div>
    );
  }

  if (mimeType === 'application/pdf') {
    return <iframe src={data.url} title="Document preview" className="h-[600px] w-full rounded-md border border-slate-200 dark:border-slate-700" />;
  }

  if (mimeType === 'image/png' || mimeType === 'image/jpeg') {
    return <img src={data.url} alt="Document preview" className="max-h-[600px] w-full rounded-md object-contain" />;
  }

  // A URL came back (thumbnail) but this component doesn't know how to
  // render this mime type inline — surface the thumbnail image itself.
  return <img src={data.url} alt="Document thumbnail" className="max-h-[600px] w-full rounded-md object-contain" />;
}
