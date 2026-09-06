/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useState } from 'react';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import { apiErrorMessage } from '../../lib/apiError';
import { useAuth } from '../auth/useAuth';
import { useUsers, useInviteUser, useUpdateUser, useDeleteUser } from './hooks';
import { InviteUserDialog } from './InviteUserDialog';
import type { Role, User } from '../../types/api';

const PAGE_SIZE = 20;

// The one account that can never be deleted from this screen, no matter who
// is signed in as ADMIN — mirrors the same guard the backend enforces
// (UsersService::delete), kept here purely so the button doesn't even
// appear rather than appearing and then failing.
const SUPER_ADMIN_EMAIL = 'arsi.india.info@gmail.com';

// §22.10 — ADMIN only (route-gated by RequireRole in router.tsx). Invite
// (no password field — the server emails one) plus per-row role/status edits.
export function UserManagementPage() {
  const { user: currentUser } = useAuth();
  const [page, setPage] = useState(1);
  const { data, isLoading, isError } = useUsers(page, PAGE_SIZE);
  const inviteUser = useInviteUser(page, PAGE_SIZE);
  const updateUser = useUpdateUser(page, PAGE_SIZE);
  const deleteUser = useDeleteUser(page, PAGE_SIZE);

  const [showInvite, setShowInvite] = useState(false);
  const [disableTarget, setDisableTarget] = useState<User | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<User | null>(null);
  const [rowError, setRowError] = useState<{ id: number; message: string } | null>(null);

  const users = data?.items ?? [];
  const totalPages = data?.totalPages ?? 1;

  async function handleRoleChange(user: User, role: Role) {
    setRowError(null);
    try {
      await updateUser.mutateAsync({ id: user.id, patch: { role } });
    } catch (err) {
      setRowError({ id: user.id, message: apiErrorMessage(err, 'Could not update role') });
    }
  }

  async function handleToggleStatus(user: User) {
    if (user.status === 'ACTIVE') {
      setDisableTarget(user);
      return;
    }
    setRowError(null);
    try {
      await updateUser.mutateAsync({ id: user.id, patch: { status: 'ACTIVE' } });
    } catch (err) {
      setRowError({ id: user.id, message: apiErrorMessage(err, 'Could not update status') });
    }
  }

  async function confirmDisable() {
    if (!disableTarget) return;
    const target = disableTarget;
    try {
      await updateUser.mutateAsync({ id: target.id, patch: { status: 'DISABLED' } });
    } catch (err) {
      setRowError({ id: target.id, message: apiErrorMessage(err, 'Could not disable user') });
    } finally {
      setDisableTarget(null);
    }
  }

  async function confirmDelete() {
    if (!deleteTarget) return;
    const target = deleteTarget;
    try {
      await deleteUser.mutateAsync(target.id);
      setDeleteTarget(null);
    } catch (err) {
      setRowError({ id: target.id, message: apiErrorMessage(err, 'Could not delete user') });
      setDeleteTarget(null);
    }
  }

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">User management</h1>
        <button
          onClick={() => setShowInvite(true)}
          className="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700"
        >
          Invite
        </button>
      </div>

      <div>
        {isLoading && (
          <div className="space-y-2" aria-label="Loading">
            {[...Array(6)].map((_, i) => (
              <div key={i} className="h-10 animate-pulse rounded-md bg-slate-200 dark:bg-slate-700" />
            ))}
          </div>
        )}

        {isError && (
          <p className="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">Couldn't load users.</p>
        )}

        {!isLoading && !isError && users.length === 0 && <p className="text-sm text-slate-500 dark:text-slate-400">No users yet.</p>}

        {!isLoading && !isError && users.length > 0 && (
          <>
            <div className="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-700">
            <table className="w-full overflow-hidden bg-white text-sm dark:bg-slate-800">
              <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-900 dark:text-slate-400">
                <tr>
                  <th className="px-4 py-2">Name</th>
                  <th className="px-4 py-2">Email</th>
                  <th className="px-4 py-2">Role</th>
                  <th className="px-4 py-2">Status</th>
                  <th className="px-4 py-2" />
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                {users.map((user) => {
                  const isSuperAdmin = user.email.toLowerCase() === SUPER_ADMIN_EMAIL;
                  return (
                  <tr key={user.id}>
                    <td className="px-4 py-2 text-slate-800 dark:text-slate-200">{user.name}</td>
                    <td className="px-4 py-2 text-slate-600 dark:text-slate-300">{user.email}</td>
                    <td className="px-4 py-2">
                      <select
                        value={user.role}
                        onChange={(e) => void handleRoleChange(user, e.target.value as Role)}
                        disabled={updateUser.isPending || isSuperAdmin}
                        className="rounded-md border border-slate-300 px-2 py-1 text-xs disabled:opacity-60 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                      >
                        <option value="ADMIN">Admin</option>
                        <option value="MANAGER">Manager</option>
                        <option value="EMPLOYEE">Employee</option>
                      </select>
                    </td>
                    <td className="px-4 py-2">
                      <span
                        className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                          user.status === 'ACTIVE'
                            ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                            : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-300'
                        }`}
                      >
                        {user.status}
                      </span>
                    </td>
                    <td className="px-4 py-2 text-right">
                      <div className="flex justify-end gap-3">
                        {!isSuperAdmin && (
                          <button
                            onClick={() => void handleToggleStatus(user)}
                            disabled={updateUser.isPending}
                            className={`text-xs hover:underline disabled:opacity-60 ${
                              user.status === 'ACTIVE' ? 'text-red-600' : 'text-blue-600'
                            }`}
                          >
                            {user.status === 'ACTIVE' ? 'Disable' : 'Enable'}
                          </button>
                        )}
                        {!isSuperAdmin && user.id !== currentUser?.id && (
                          <button
                            onClick={() => setDeleteTarget(user)}
                            disabled={deleteUser.isPending}
                            className="text-xs text-red-600 hover:underline disabled:opacity-60"
                          >
                            Delete
                          </button>
                        )}
                      </div>
                      {rowError?.id === user.id && <p className="mt-1 text-xs text-red-600">{rowError.message}</p>}
                    </td>
                  </tr>
                  );
                })}
              </tbody>
            </table>
            </div>

            {totalPages > 1 && (
              <div className="mt-4 flex items-center justify-end gap-2 text-sm">
                <button
                  onClick={() => setPage((p) => Math.max(1, p - 1))}
                  disabled={page <= 1}
                  className="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 hover:bg-slate-100 disabled:opacity-40 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                  Previous
                </button>
                <span className="text-xs text-slate-500 dark:text-slate-400">
                  Page {page} of {totalPages}
                </span>
                <button
                  onClick={() => setPage((p) => Math.min(totalPages, p + 1))}
                  disabled={page >= totalPages}
                  className="rounded-md border border-slate-300 px-2.5 py-1.5 text-xs text-slate-700 hover:bg-slate-100 disabled:opacity-40 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
                >
                  Next
                </button>
              </div>
            )}
          </>
        )}
      </div>

      {showInvite && <InviteUserDialog onInvite={(payload) => inviteUser.mutateAsync(payload)} onClose={() => setShowInvite(false)} />}

      {disableTarget && (
        <ConfirmDialog
          title={`Disable ${disableTarget.name}?`}
          message="They will be signed out immediately and lose all access."
          confirmLabel="Disable"
          onConfirm={confirmDisable}
          onCancel={() => setDisableTarget(null)}
        />
      )}

      {deleteTarget && (
        <ConfirmDialog
          title={`Delete ${deleteTarget.name}?`}
          message="This permanently deletes the account — it cannot be undone. Fails if they've created folders or documents that still exist."
          confirmLabel="Delete"
          onConfirm={confirmDelete}
          onCancel={() => setDeleteTarget(null)}
        />
      )}
    </div>
  );
}

