/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { Fragment, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { FileText, FolderOpen, Link2, ScrollText, Users as UsersIcon, type LucideIcon } from 'lucide-react';
import { PageHeader } from '../../components/PageHeader';
import { fetchAuditLogs, type AuditLogFilters } from './api';
import type { AuditLogEntry } from '../../types/api';

const ENTITY_ICON: Record<AuditLogEntry['entityType'], { icon: LucideIcon; className: string }> = {
  DOCUMENT: { icon: FileText, className: 'bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400' },
  FOLDER: { icon: FolderOpen, className: 'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400' },
  USER: { icon: UsersIcon, className: 'bg-purple-100 text-purple-600 dark:bg-purple-950 dark:text-purple-400' },
  SHARE_LINK: { icon: Link2, className: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400' },
};

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
    <div>
      <PageHeader
        icon={ScrollText}
        iconClassName="bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-400"
        title="Audit log"
        subtitle={data ? `${data.total} matching event${data.total === 1 ? '' : 's'}` : undefined}
      />

      <div className="mb-4 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-700 dark:bg-slate-800">
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
        <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
        <table className="w-full overflow-hidden bg-white text-sm dark:bg-slate-800">
          <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500 dark:bg-slate-900 dark:text-slate-400">
            <tr>
              <th className="px-4 py-2">When</th>
              <th className="px-4 py-2">Action</th>
              <th className="px-4 py-2">Entity</th>
              <th className="px-4 py-2">User</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
            {data.items.map((entry) => {
              const entityIcon = ENTITY_ICON[entry.entityType];
              const EntityIcon = entityIcon.icon;
              return (
              <Fragment key={entry.id}>
                <tr
                  className="cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50"
                  onClick={() => setExpandedId(expandedId === entry.id ? null : entry.id)}
                >
                  <td className="px-4 py-2 text-slate-600 dark:text-slate-300">{entry.createdAt}</td>
                  <td className="px-4 py-2 font-medium text-slate-800 dark:text-slate-200">{entry.action}</td>
                  <td className="px-4 py-2 text-slate-600 dark:text-slate-300">
                    <span className="flex items-center gap-2">
                      <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-md ${entityIcon.className}`}>
                        <EntityIcon size={13} />
                      </span>
                      {entry.entityType} #{entry.entityId}
                    </span>
                  </td>
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
              );
            })}
          </tbody>
        </table>
        </div>
      )}
    </div>
  );
}

