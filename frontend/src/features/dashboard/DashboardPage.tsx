/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { useAuth } from '../auth/useAuth';
import { fetchDashboardSummary } from './api';
import { formatBytes } from '../../lib/fileTypes';
import type { RecentDocumentActivity } from '../../types/api';

function activityLabel(action: RecentDocumentActivity['action']): string {
  switch (action) {
    case 'DOCUMENT_DOWNLOADED':
      return 'Downloaded';
    case 'DOCUMENT_VERSION_UPLOADED':
      return 'New version uploaded';
    default:
      return action;
  }
}

// §22.2 — recent activity, storage summary, and shared-folder quick links.
export function DashboardPage() {
  const { user } = useAuth();
  const { data, isLoading, isError } = useQuery({
    queryKey: ['dashboard'],
    queryFn: fetchDashboardSummary,
  });

  const recentDocuments = data?.recentDocuments ?? [];
  const sharedFolders = data?.sharedFolders ?? [];
  const storageUsedBytes = data?.storageUsedBytes ?? 0;
  const isEmpty = !isLoading && !isError && recentDocuments.length === 0 && sharedFolders.length === 0 && storageUsedBytes === 0;

  return (
    <div>
      <div className="mb-6">
        <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Welcome, {user?.name}</h1>
        <p className="text-sm text-slate-500 dark:text-slate-400">
          Signed in as {user?.role.toLowerCase()} · {user?.email}
        </p>
      </div>

      <div>
        {isLoading && (
          <div className="space-y-2" aria-label="Loading">
            {[...Array(4)].map((_, i) => (
              <div key={i} className="h-16 animate-pulse rounded-md bg-slate-200 dark:bg-slate-700" />
            ))}
          </div>
        )}

        {isError && (
          <p className="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">Couldn't load the dashboard.</p>
        )}

        {isEmpty && (
          <div className="flex flex-col items-center justify-center gap-3 py-16 text-center">
            <p className="text-sm text-slate-500 dark:text-slate-400">Nothing here yet — browse your folders to get started</p>
            <Link to="/browse" className="rounded-md bg-blue-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-blue-700">
              Browse folders
            </Link>
          </div>
        )}

        {!isLoading && !isError && !isEmpty && (
          <div className="grid gap-6 lg:grid-cols-3">
            <section className="lg:col-span-2">
              <h2 className="mb-2 text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Recently accessed</h2>
              {recentDocuments.length === 0 ? (
                <p className="text-sm text-slate-500 dark:text-slate-400">No recent activity.</p>
              ) : (
                <ul className="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800">
                  {recentDocuments.map((activity) => (
                    <li key={`${activity.documentId}-${activity.at}`} className="flex items-center justify-between px-4 py-3">
                      <Link
                        to={`/browse/${activity.folderId}`}
                        className="text-sm text-slate-800 hover:text-blue-600 hover:underline dark:text-slate-200 dark:hover:text-blue-400"
                      >
                        {activity.name}
                      </Link>
                      <span className="text-xs text-slate-500 dark:text-slate-400">
                        {activityLabel(activity.action)} · {activity.at}
                      </span>
                    </li>
                  ))}
                </ul>
              )}
            </section>

            <div className="space-y-6">
              <section>
                <h2 className="mb-2 text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Storage used</h2>
                <div className="rounded-lg border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800">
                  <p className="text-2xl font-semibold text-slate-900 dark:text-slate-100">{formatBytes(storageUsedBytes)}</p>
                </div>
              </section>

              <section>
                <h2 className="mb-2 text-sm font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Folders shared with you</h2>
                {sharedFolders.length === 0 ? (
                  <p className="text-sm text-slate-500 dark:text-slate-400">No shared folders.</p>
                ) : (
                  <ul className="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800">
                    {sharedFolders.map((folder) => (
                      <li key={folder.id} className="px-4 py-3">
                        <Link
                          to={`/browse/${folder.id}`}
                          className="text-sm text-slate-800 hover:text-blue-600 hover:underline dark:text-slate-200 dark:hover:text-blue-400"
                        >
                          <span aria-hidden>📁</span> {folder.name}
                        </Link>
                      </li>
                    ))}
                  </ul>
                )}
              </section>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

