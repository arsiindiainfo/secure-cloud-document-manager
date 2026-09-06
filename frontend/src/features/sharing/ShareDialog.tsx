/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useEffect, useRef, useState } from 'react';
import type { Permission } from '../../types/api';
import { apiErrorMessage } from '../../lib/apiError';
import { ConfirmDialog } from '../../components/ConfirmDialog';
import {
  useGrants,
  useGrantAccess,
  useRevokeAccess,
  useShareLinks,
  useCreateShareLink,
  useRevokeShareLink,
  useUserSearch,
} from './hooks';
import type { ShareTarget, SharePermission } from './api';

interface ShareDialogProps {
  target: ShareTarget;
  effectivePermission: Permission;
  onClose: () => void;
}

const EXPIRY_OPTIONS = [
  { label: '1 day', hours: 24 },
  { label: '7 days', hours: 168 },
];

/** §22.5 — internal grants (OWNER only) + external expiring links (documents only, OWNER/EDITOR). */
export function ShareDialog({ target, effectivePermission, onClose }: ShareDialogProps) {
  const canManageGrants = effectivePermission === 'OWNER';
  const canManageLinks = target.type === 'document' && (effectivePermission === 'OWNER' || effectivePermission === 'EDITOR');

  const grants = useGrants(target);
  const grantAccess = useGrantAccess(target);
  const revokeAccess = useRevokeAccess(target);
  const shareLinks = useShareLinks(target.type === 'document' ? target.id : 0);
  const createLink = useCreateShareLink(target.type === 'document' ? target.id : 0);
  const revokeLink = useRevokeShareLink(target.type === 'document' ? target.id : 0);

  const [email, setEmail] = useState('');
  const [permission, setPermission] = useState<Permission>('VIEWER');
  const [grantError, setGrantError] = useState<string | null>(null);
  const [showSuggestions, setShowSuggestions] = useState(false);
  const suggestionsRef = useRef<HTMLDivElement>(null);
  const userSearch = useUserSearch(email);

  const [linkPermission, setLinkPermission] = useState<SharePermission>('DOWNLOAD');
  const [expiresInHours, setExpiresInHours] = useState(24);
  const [maxDownloads, setMaxDownloads] = useState('');
  const [linkError, setLinkError] = useState<string | null>(null);
  const [createdUrl, setCreatedUrl] = useState<string | null>(null);
  const [copyLabel, setCopyLabel] = useState('Copy link');

  const [revokeUserTarget, setRevokeUserTarget] = useState<number | null>(null);
  const [revokeLinkTarget, setRevokeLinkTarget] = useState<number | null>(null);

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (suggestionsRef.current && !suggestionsRef.current.contains(e.target as Node)) setShowSuggestions(false);
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  async function handleGrant(e: React.FormEvent) {
    e.preventDefault();
    setGrantError(null);
    try {
      await grantAccess.mutateAsync({ email: email.trim(), permission });
      setEmail('');
      setPermission('VIEWER');
    } catch (err) {
      setGrantError(apiErrorMessage(err, 'Could not grant access'));
    }
  }

  async function handleCreateLink(e: React.FormEvent) {
    e.preventDefault();
    setLinkError(null);
    setCreatedUrl(null);
    try {
      const result = await createLink.mutateAsync({
        permission: linkPermission,
        expiresInHours,
        maxDownloads: maxDownloads.trim() === '' ? undefined : Number(maxDownloads),
      });
      setCreatedUrl(result.url);
      setMaxDownloads('');
    } catch (err) {
      setLinkError(apiErrorMessage(err, 'Could not create link'));
    }
  }

  async function handleCopy() {
    if (!createdUrl) return;
    try {
      await navigator.clipboard.writeText(createdUrl);
      setCopyLabel('Copied!');
      setTimeout(() => setCopyLabel('Copy link'), 2000);
    } catch {
      // clipboard access denied — the URL is still visible to select/copy manually
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-lg rounded-lg bg-white p-6 shadow-lg dark:bg-slate-800">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-base font-semibold text-slate-900 dark:text-slate-100">Share {target.type === 'folder' ? 'folder' : ''}</h2>
          <button onClick={onClose} className="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300" aria-label="Close">
            ✕
          </button>
        </div>

        {canManageGrants && (
          <section className="mb-6">
            <h3 className="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">Invite a person</h3>
            <form onSubmit={(e) => void handleGrant(e)} className="flex gap-2">
              <div ref={suggestionsRef} className="relative flex-1">
                <input
                  type="email"
                  required
                  autoComplete="off"
                  placeholder="name@company.com"
                  value={email}
                  onChange={(e) => {
                    setEmail(e.target.value);
                    setShowSuggestions(true);
                  }}
                  onFocus={() => setShowSuggestions(true)}
                  className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                />
                {showSuggestions && (userSearch.data?.length ?? 0) > 0 && (
                  <ul className="absolute z-10 mt-1 w-full rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
                    {userSearch.data!.map((u) => (
                      <li key={u.id}>
                        <button
                          type="button"
                          onClick={() => {
                            setEmail(u.email);
                            setShowSuggestions(false);
                          }}
                          className="flex w-full flex-col px-3 py-1.5 text-left hover:bg-slate-50 dark:hover:bg-slate-700/50"
                        >
                          <span className="text-sm text-slate-800 dark:text-slate-200">{u.name}</span>
                          <span className="text-xs text-slate-400">{u.email}</span>
                        </button>
                      </li>
                    ))}
                  </ul>
                )}
              </div>
              <select
                value={permission}
                onChange={(e) => setPermission(e.target.value as Permission)}
                className="rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              >
                <option value="VIEWER">Viewer</option>
                <option value="EDITOR">Editor</option>
                <option value="OWNER">Owner</option>
              </select>
              <button
                type="submit"
                disabled={grantAccess.isPending || email.trim() === ''}
                className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
              >
                Invite
              </button>
            </form>
            {grantError && <p className="mt-2 text-sm text-red-600">{grantError}</p>}

            <ul className="mt-3 space-y-1">
              {(grants.data ?? []).map((g) => (
                <li key={g.userId} className="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700/50">
                  <span className="text-slate-700 dark:text-slate-300">
                    {g.name} <span className="text-xs text-slate-400">· {g.permission}</span>
                  </span>
                  <button onClick={() => setRevokeUserTarget(g.userId)} className="text-xs text-red-600 hover:underline">
                    Revoke
                  </button>
                </li>
              ))}
            </ul>
          </section>
        )}

        {canManageLinks && (
          <section>
            <h3 className="mb-2 text-sm font-medium text-slate-700 dark:text-slate-300">External link</h3>
            <form onSubmit={(e) => void handleCreateLink(e)} className="flex flex-wrap items-end gap-2">
              <select
                value={linkPermission}
                onChange={(e) => setLinkPermission(e.target.value as SharePermission)}
                className="rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              >
                <option value="VIEW">View</option>
                <option value="DOWNLOAD">Download</option>
              </select>
              <select
                value={expiresInHours}
                onChange={(e) => setExpiresInHours(Number(e.target.value))}
                className="rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              >
                {EXPIRY_OPTIONS.map((opt) => (
                  <option key={opt.hours} value={opt.hours}>
                    {opt.label}
                  </option>
                ))}
              </select>
              <input
                type="number"
                min={1}
                max={1000}
                placeholder="Max downloads (optional)"
                value={maxDownloads}
                onChange={(e) => setMaxDownloads(e.target.value)}
                className="w-44 rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              />
              <button
                type="submit"
                disabled={createLink.isPending}
                className="rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
              >
                Generate link
              </button>
            </form>
            {linkError && <p className="mt-2 text-sm text-red-600">{linkError}</p>}

            {createdUrl && (
              <div className="mt-3 flex items-center gap-2 rounded-md bg-slate-50 p-2 dark:bg-slate-900">
                <input readOnly value={createdUrl} className="flex-1 bg-transparent text-sm text-slate-700 dark:text-slate-300" />
                <button onClick={() => void handleCopy()} className="text-xs font-medium text-blue-600 hover:underline">
                  {copyLabel}
                </button>
              </div>
            )}

            <ul className="mt-3 space-y-1">
              {(shareLinks.data ?? []).map((link) => {
                const status = link.revokedAt ? 'Revoked' : new Date(link.expiresAt) < new Date() ? 'Expired' : 'Active';
                return (
                  <li key={link.id} className="flex items-center justify-between rounded-md px-2 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-slate-700/50">
                    <span className="text-slate-700 dark:text-slate-300">
                      {link.permission} <span className="text-xs text-slate-400">· {status} · {link.downloadCount} download{link.downloadCount === 1 ? '' : 's'}</span>
                    </span>
                    {status === 'Active' && (
                      <button onClick={() => setRevokeLinkTarget(link.id)} className="text-xs text-red-600 hover:underline">
                        Revoke
                      </button>
                    )}
                  </li>
                );
              })}
            </ul>
          </section>
        )}

        {revokeUserTarget !== null && (
          <ConfirmDialog
            title="Revoke access?"
            message="This cannot be undone — they will lose access immediately."
            confirmLabel="Revoke"
            onConfirm={async () => {
              await revokeAccess.mutateAsync(revokeUserTarget);
              setRevokeUserTarget(null);
            }}
            onCancel={() => setRevokeUserTarget(null)}
          />
        )}

        {revokeLinkTarget !== null && (
          <ConfirmDialog
            title="Revoke this link?"
            message="This cannot be undone — they will lose access immediately."
            confirmLabel="Revoke"
            onConfirm={async () => {
              await revokeLink.mutateAsync(revokeLinkTarget);
              setRevokeLinkTarget(null);
            }}
            onCancel={() => setRevokeLinkTarget(null)}
          />
        )}
      </div>
    </div>
  );
}
