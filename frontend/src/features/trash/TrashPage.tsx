import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { BrandLogo } from '../../components/BrandLogo';
import { apiErrorMessage } from '../../lib/apiError';
import { useTrash, useRestoreTrashedFolder, useRestoreTrashedDocument } from './hooks';
import type { TrashedDocument, TrashedFolder } from '../../types/api';

function daysRemainingLabel(daysRemaining: number): string {
  if (daysRemaining === 0) return 'Purging today';
  return `${daysRemaining} day${daysRemaining === 1 ? '' : 's'} until permanent removal`;
}

// §22.8 — soft-deleted folders/documents, each restorable individually.
// Restoring a folder whose own parent is still deleted comes back as a 422
// PARENT_FOLDER_NOT_FOUND — surfaced inline on that row rather than as a toast.
export function TrashPage() {
  const navigate = useNavigate();
  const { data, isLoading, isError } = useTrash();
  const restoreFolder = useRestoreTrashedFolder();
  const restoreDocument = useRestoreTrashedDocument();
  const [rowErrors, setRowErrors] = useState<Record<string, string>>({});

  const folders = data?.folders ?? [];
  const documents = data?.documents ?? [];
  const isEmpty = !isLoading && !isError && folders.length === 0 && documents.length === 0;

  async function handleRestoreFolder(folder: TrashedFolder) {
    const rowKey = `folder-${folder.id}`;
    setRowErrors((prev) => ({ ...prev, [rowKey]: '' }));
    try {
      await restoreFolder.mutateAsync(folder.id);
    } catch (err) {
      setRowErrors((prev) => ({ ...prev, [rowKey]: apiErrorMessage(err, 'Could not restore folder') }));
    }
  }

  async function handleRestoreDocument(document: TrashedDocument) {
    const rowKey = `document-${document.id}`;
    setRowErrors((prev) => ({ ...prev, [rowKey]: '' }));
    try {
      await restoreDocument.mutateAsync(document.id);
    } catch (err) {
      setRowErrors((prev) => ({ ...prev, [rowKey]: apiErrorMessage(err, 'Could not restore document') }));
    }
  }

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900">
      <header className="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-3 dark:border-slate-700 dark:bg-slate-800">
        <div className="flex items-center gap-4">
          <BrandLogo className="h-6" />
          <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Trash</h1>
        </div>
        <button
          onClick={() => navigate('/browse')}
          className="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
        >
          Back to Browse
        </button>
      </header>

      <div className="p-6">
        {isLoading && (
          <div className="space-y-2" aria-label="Loading">
            {[...Array(4)].map((_, i) => (
              <div key={i} className="h-14 animate-pulse rounded-md bg-slate-200 dark:bg-slate-700" />
            ))}
          </div>
        )}

        {isError && (
          <p className="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">Couldn't load Trash.</p>
        )}

        {isEmpty && <p className="text-sm text-slate-500 dark:text-slate-400">Trash is empty.</p>}

        {!isLoading && !isError && folders.length > 0 && (
          <section className="mb-6">
            <h2 className="mb-2 text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Folders</h2>
            <ul className="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800">
              {folders.map((folder) => {
                const rowKey = `folder-${folder.id}`;
                return (
                  <li key={rowKey} className="flex items-center justify-between px-4 py-3">
                    <div>
                      <p className="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                        <span aria-hidden>📁</span> {folder.name}
                      </p>
                      <p className="text-xs text-slate-500 dark:text-slate-400">{daysRemainingLabel(folder.daysRemaining)}</p>
                      {rowErrors[rowKey] && <p className="mt-1 text-xs text-red-600">{rowErrors[rowKey]}</p>}
                    </div>
                    <button
                      onClick={() => void handleRestoreFolder(folder)}
                      disabled={restoreFolder.isPending}
                      className="rounded-md border border-slate-300 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-100 disabled:opacity-60 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                      Restore
                    </button>
                  </li>
                );
              })}
            </ul>
          </section>
        )}

        {!isLoading && !isError && documents.length > 0 && (
          <section>
            <h2 className="mb-2 text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Documents</h2>
            <ul className="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800">
              {documents.map((document) => {
                const rowKey = `document-${document.id}`;
                return (
                  <li key={rowKey} className="flex items-center justify-between px-4 py-3">
                    <div>
                      <p className="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                        <span aria-hidden>📄</span> {document.name}
                      </p>
                      <p className="text-xs text-slate-500 dark:text-slate-400">{daysRemainingLabel(document.daysRemaining)}</p>
                      {rowErrors[rowKey] && <p className="mt-1 text-xs text-red-600">{rowErrors[rowKey]}</p>}
                    </div>
                    <button
                      onClick={() => void handleRestoreDocument(document)}
                      disabled={restoreDocument.isPending}
                      className="rounded-md border border-slate-300 px-3 py-1.5 text-xs text-slate-700 hover:bg-slate-100 disabled:opacity-60 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                    >
                      Restore
                    </button>
                  </li>
                );
              })}
            </ul>
          </section>
        )}
      </div>
    </div>
  );
}
