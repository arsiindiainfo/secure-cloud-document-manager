/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useState } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { searchDocuments } from './api';
import { formatBytes } from '../../lib/fileTypes';
import { DocumentDetailPanel } from '../documents/DocumentDetailPanel';

// §22.7 — results carry the folder breadcrumb for context since matches
// can come from anywhere the caller has access to, not just the current folder.
export function SearchResultsPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const query = searchParams.get('q') ?? '';
  const [openDocumentId, setOpenDocumentId] = useState<number | null>(null);

  const { data, isLoading, isError } = useQuery({
    queryKey: ['search', query],
    queryFn: () => searchDocuments(query),
    enabled: query.trim() !== '',
  });

  const results = data?.items ?? [];

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">Search results for "{query}"</h1>
        <button
          onClick={() => navigate('/browse')}
          className="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700"
        >
          Back to browse
        </button>
      </div>

      {isLoading && (
        <div className="space-y-2" aria-label="Loading">
          {[...Array(4)].map((_, i) => (
            <div key={i} className="h-14 animate-pulse rounded-md bg-slate-200 dark:bg-slate-700" />
          ))}
        </div>
      )}

      {isError && (
        <p className="rounded-md bg-red-50 p-4 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">Couldn't run this search.</p>
      )}

      {!isLoading && !isError && query.trim() !== '' && results.length === 0 && (
        <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
          <p className="text-sm text-slate-500 dark:text-slate-400">No documents match "{query}"</p>
        </div>
      )}

      {results.length > 0 && (
        <ul className="divide-y divide-slate-200 rounded-lg border border-slate-200 bg-white dark:divide-slate-700 dark:border-slate-700 dark:bg-slate-800">
          {results.map((doc) => (
            <li
              key={doc.id}
              className="flex cursor-pointer items-center justify-between px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50"
              onClick={() => setOpenDocumentId(doc.id)}
            >
              <span className="flex flex-col">
                <span className="flex items-center gap-2 text-sm text-slate-800 dark:text-slate-200">
                  <span aria-hidden>📄</span> {doc.name}
                </span>
                <span className="text-xs text-slate-400">{doc.folderName} · {formatBytes(doc.sizeBytes)}</span>
              </span>
            </li>
          ))}
        </ul>
      )}

      {openDocumentId !== null && (
        <DocumentDetailPanel
          documentId={openDocumentId}
          folderId={results.find((r) => r.id === openDocumentId)?.folderId ?? 0}
          onClose={() => setOpenDocumentId(null)}
        />
      )}
    </div>
  );
}

