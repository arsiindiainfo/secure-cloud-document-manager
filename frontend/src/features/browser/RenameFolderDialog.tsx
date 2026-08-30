import { useState } from 'react';
import { apiErrorMessage } from '../../lib/apiError';
import type { Folder } from '../../types/api';

interface RenameFolderDialogProps {
  folder: Folder;
  onRename: (name: string) => Promise<unknown>;
  onClose: () => void;
}

export function RenameFolderDialog({ folder, onRename, onClose }: RenameFolderDialogProps) {
  const [name, setName] = useState(folder.name);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await onRename(name.trim());
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, 'Could not rename folder'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <form onSubmit={handleSubmit} className="w-full max-w-sm rounded-lg bg-white p-6 shadow-lg dark:bg-slate-800">
        <h2 className="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Rename folder</h2>
        <label htmlFor="rename-folder-name" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
          Name <span aria-hidden>*</span>
        </label>
        <input
          id="rename-folder-name"
          autoFocus
          value={name}
          onChange={(e) => setName(e.target.value)}
          className="mb-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
        />
        {error && <p className="mb-3 text-sm text-red-600">{error}</p>}

        <div className="mt-4 flex justify-end gap-2">
          <button
            type="button"
            onClick={onClose}
            className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
          >
            Cancel
          </button>
          <button
            type="submit"
            disabled={isSubmitting || name.trim() === '' || name.trim() === folder.name}
            className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
          >
            {isSubmitting ? 'Saving…' : 'Save'}
          </button>
        </div>
      </form>
    </div>
  );
}
