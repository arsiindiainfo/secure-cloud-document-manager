import { useState } from 'react';
import { apiErrorMessage } from '../../lib/apiError';
import type { Role } from '../../types/api';
import type { InviteUserPayload } from './api';

interface InviteUserDialogProps {
  onInvite: (payload: InviteUserPayload) => Promise<unknown>;
  onClose: () => void;
}

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function InviteUserDialog({ onInvite, onClose }: InviteUserDialogProps) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [role, setRole] = useState<Role>('EMPLOYEE');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const isEmailValid = EMAIL_PATTERN.test(email.trim());
  const canSubmit = name.trim() !== '' && isEmailValid;

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!canSubmit) return;
    setError(null);
    setIsSubmitting(true);
    try {
      await onInvite({ name: name.trim(), email: email.trim(), role });
      onClose();
    } catch (err) {
      setError(apiErrorMessage(err, 'Could not invite user'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <form onSubmit={handleSubmit} className="w-full max-w-sm rounded-lg bg-white p-6 shadow-lg dark:bg-slate-800">
        <h2 className="mb-4 text-base font-semibold text-slate-900 dark:text-slate-100">Invite user</h2>

        <label htmlFor="invite-name" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
          Name <span aria-hidden>*</span>
        </label>
        <input
          id="invite-name"
          autoFocus
          value={name}
          onChange={(e) => setName(e.target.value)}
          className="mb-3 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
        />

        <label htmlFor="invite-email" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
          Email <span aria-hidden>*</span>
        </label>
        <input
          id="invite-email"
          type="email"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          className="mb-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
        />
        {email.trim() !== '' && !isEmailValid && <p className="mb-2 text-xs text-red-600">Enter a valid email address.</p>}

        <label htmlFor="invite-role" className="mb-1 mt-2 block text-sm font-medium text-slate-700 dark:text-slate-300">
          Role <span aria-hidden>*</span>
        </label>
        <select
          id="invite-role"
          value={role}
          onChange={(e) => setRole(e.target.value as Role)}
          className="mb-1 w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
        >
          <option value="ADMIN">Admin</option>
          <option value="MANAGER">Manager</option>
          <option value="EMPLOYEE">Employee</option>
        </select>

        {error && <p className="mb-3 mt-2 text-sm text-red-600">{error}</p>}

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
            disabled={isSubmitting || !canSubmit}
            className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
          >
            {isSubmitting ? 'Inviting…' : 'Invite'}
          </button>
        </div>
      </form>
    </div>
  );
}
