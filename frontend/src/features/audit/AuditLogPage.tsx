/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { Fragment, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { fetchAuditLogs, type AuditLogFilters } from './api';

// §22.9 — ADMIN only (route-gated by role in App.tsx/router); filterable
// table, each row expands to show the raw details JSON.
export function AuditLogPage() {
  const [filters, setFilters] = useState<AuditLogFilters>({});
  const [expandedId, setExpandedId] = useState<number | null>(null);

  const { data, isLoading, isError } = useQuery({
    queryKey: ['audit-logs', filters],
    queryFn: () => fetchAuditLogs(filters),
  });

  function updateFilter<K extends keyof AuditLogFilters>(key: K, value: string) {
    setFilters((prev) => ({ ...prev, [key]: value === '' ? undefined : value }));
  }

  return (
    <div className="min-h-screen bg-slate-50 p-6 dark:bg-slate-900">
      <h1 className="mb-4 text-lg font-semibold text-slate-900 dark:text-slate-100">Audit log</h1>

      <div className="mb-4 flex flex-wrap gap-2">
        <input
          placeholder="Action (e.g. DOCUMENT_DOWNLOADED)"
          onChange={(e) => updateFilter('action', e.target.value)}
          className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
        />
        <select
          onChange={(e) => updateFilter('entityType', e.target.value)}
          className="rounded-md border border-slate-300 px-2 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
        >
          <option value="">All entity types</option>
          <option value="DOCUMENT">Document</option>
          <option value="FOLDER">Folder</option>
          <option value="USER">User</option>
          <option value="SHARE_LINK">Share link</option>
        </select>
        <input
          type="date"
          onChange={(e) => updateFilter('dateFrom', e.target.value)}
          className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
        />
        <input
          type="date"
          onChange={(e) => updateFilter('dateTo', e.target.value)}
          className="rounded-md border border-slate-300 px-3 py-2 text-sm dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
        />
      </div>

      {isLoading && (
        <div className="space-y-2" aria-label="Loading">
          {[...Array(6)].map((_, i) => (
            <div key={i} className="h-10 animate-pulse rounded-md bg-slate-200 dark:bg-slate-700" />
          ))}
        </div>
      )}

      {isError && <p className="text-sm text-red-600">Couldn't load the audit log.</p>}

      {data && data.items.length === 0 && <p className="text-sm text-slate-500 dark:text-slate-400">No matching events.</p>}

      {data && data.items.length > 0 && (
        <table className="w-full overflow-hidden rounded-lg border border-slate-200 bg-white text-sm dark:border-slate-700 dark:bg-slate-800">
          <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr>
              <th className="px-4 py-2">When</th>
              <th className="px-4 py-2">Action</th>
              <th className="px-4 py-2">Entity</th>
              <th className="px-4 py-2">User</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
            {data.items.map((entry) => (
              <Fragment key={entry.id}>
                <tr
                  className="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50"
                  onClick={() => setExpandedId(expandedId === entry.id ? null : entry.id)}
                >
                  <td className="px-4 py-2 text-slate-600 dark:text-slate-300">{entry.createdAt}</td>
                  <td className="px-4 py-2 font-medium text-slate-800 dark:text-slate-200">{entry.action}</td>
                  <td className="px-4 py-2 text-slate-600 dark:text-slate-300">{entry.entityType} #{entry.entityId}</td>
                  <td className="px-4 py-2 text-slate-600 dark:text-slate-300">{entry.userId ?? '—'}</td>
                </tr>
                {expandedId === entry.id && (
                  <tr>
                    <td colSpan={4} className="bg-slate-50 px-4 py-2 dark:bg-slate-900">
                      <pre className="overflow-x-auto text-xs text-slate-600 dark:text-slate-300">{JSON.stringify(entry.details, null, 2)}</pre>
                    </td>
                  </tr>
                )}
              </Fragment>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

