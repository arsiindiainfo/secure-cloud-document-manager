import { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import * as api from './api';
import { apiErrorMessage } from '../../lib/apiError';
import { formatBytes } from '../../lib/fileTypes';
import { folderChildrenKey } from '../browser/hooks';
import { FilePreview } from '../../components/FilePreview';
import { ShareDialog } from '../sharing/ShareDialog';

interface DocumentDetailPanelProps {
  documentId: number;
  folderId: number;
  onClose: () => void;
}

/**
 * §22.4 — slide-over panel. Only the metadata tab is built in Phase 1;
 * Preview/Sharing/Activity land once download/preview (Phase 2) and
 * sharing (Phase 3) exist.
 */
export function DocumentDetailPanel({ documentId, folderId, onClose }: DocumentDetailPanelProps) {
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['document', documentId],
    queryFn: () => api.fetchDocument(documentId),
    // §9.3/§22.4 — poll while the Lambda's callback hasn't landed yet, so
    // "generating preview…" flips to the real preview without a manual refresh.
    refetchInterval: (query) => (query.state.data?.processingStatus === 'PENDING' || query.state.data?.processingStatus === 'PROCESSING' ? 3000 : false),
  });

  const [activeTab, setActiveTab] = useState<'details' | 'preview'>('details');
  const [isEditing, setIsEditing] = useState(false);
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [tags, setTags] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [downloadError, setDownloadError] = useState<string | null>(null);
  const [showShareDialog, setShowShareDialog] = useState(false);

  async function handleDownload() {
    setDownloadError(null);
    try {
      const result = await api.fetchDownloadUrl(documentId);
      window.open(result.url, '_blank', 'noopener,noreferrer');
    } catch (err) {
      setDownloadError(apiErrorMessage(err, 'Could not generate a download link'));
    }
  }

  function startEditing() {
    if (!data) return;
    setName(data.name);
    setDescription(data.description ?? '');
    setTags(data.tags.join(', '));
    setIsEditing(true);
  }

  async function handleSave() {
    setError(null);
    setIsSaving(true);
    try {
      await api.updateDocument(documentId, { name, description, tags });
      await queryClient.invalidateQueries({ queryKey: ['document', documentId] });
      await queryClient.invalidateQueries({ queryKey: folderChildrenKey(folderId) });
      setIsEditing(false);
    } catch (err) {
      setError(apiErrorMessage(err, 'Could not save changes'));
    } finally {
      setIsSaving(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex justify-end bg-black/30">
      <div className="h-full w-full max-w-md overflow-y-auto bg-white p-6 shadow-xl dark:bg-slate-800">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Document details</h2>
          <button onClick={onClose} className="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300" aria-label="Close">
            ✕
          </button>
        </div>

        {isLoading && <div className="h-32 animate-pulse rounded-md bg-slate-200 dark:bg-slate-700" />}

        {data && (
          <div className="mb-4 flex gap-1 border-b border-slate-200 dark:border-slate-700">
            {(['details', 'preview'] as const).map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={`px-3 py-2 text-sm capitalize ${
                  activeTab === tab
                    ? 'border-b-2 border-blue-600 font-medium text-blue-600 dark:text-blue-400'
                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                }`}
              >
                {tab}
              </button>
            ))}
          </div>
        )}

        {data && activeTab === 'preview' && (
          <div className="space-y-3">
            <FilePreview documentId={documentId} mimeType={data.currentVersionDetail?.mimeType ?? ''} processingStatus={data.processingStatus} />
            <button
              onClick={() => void handleDownload()}
              className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
            >
              Download
            </button>
            {downloadError && <p className="text-sm text-red-600">{downloadError}</p>}
          </div>
        )}

        {data && activeTab === 'details' && !isEditing && (
          <div className="space-y-3 text-sm">
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Name</p>
              <p className="text-slate-800 dark:text-slate-200">{data.name}</p>
            </div>
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Description</p>
              <p className="text-slate-800 dark:text-slate-200">{data.description || '—'}</p>
            </div>
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Tags</p>
              <p className="text-slate-800 dark:text-slate-200">{data.tags.length > 0 ? data.tags.join(', ') : '—'}</p>
            </div>
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Current version</p>
              <p className="text-slate-800 dark:text-slate-200">
                v{data.currentVersion}
                {data.currentVersionDetail && ` · ${formatBytes(data.currentVersionDetail.sizeBytes)} · ${data.currentVersionDetail.mimeType}`}
              </p>
            </div>
            <div>
              <p className="text-xs font-medium uppercase tracking-wide text-slate-400">Your access</p>
              <p className="text-slate-800 dark:text-slate-200">{data.effectivePermission}</p>
            </div>

            <div className="mt-2 flex gap-2">
              {(data.effectivePermission === 'EDITOR' || data.effectivePermission === 'OWNER') && (
                <button
                  onClick={startEditing}
                  className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                  Edit
                </button>
              )}
              {(data.effectivePermission === 'EDITOR' || data.effectivePermission === 'OWNER') && (
                <button
                  onClick={() => setShowShareDialog(true)}
                  className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                  Share
                </button>
              )}
            </div>
          </div>
        )}

        {data && activeTab === 'details' && isEditing && (
          <div className="space-y-3">
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Name *</label>
              <input
                value={name}
                onChange={(e) => setName(e.target.value)}
                maxLength={200}
                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Description</label>
              <textarea
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                maxLength={500}
                rows={3}
                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              />
            </div>
            <div>
              <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Tags (comma-separated)</label>
              <input
                value={tags}
                onChange={(e) => setTags(e.target.value)}
                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              />
            </div>

            {error && <p className="text-sm text-red-600">{error}</p>}

            <div className="flex gap-2">
              <button
                onClick={() => void handleSave()}
                disabled={isSaving || name.trim() === ''}
                className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
              >
                {isSaving ? 'Saving…' : 'Save'}
              </button>
              <button
                onClick={() => setIsEditing(false)}
                className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
              >
                Cancel
              </button>
            </div>
          </div>
        )}
      </div>

      {showShareDialog && data && (
        <ShareDialog documentId={documentId} effectivePermission={data.effectivePermission} onClose={() => setShowShareDialog(false)} />
      )}
    </div>
  );
}
