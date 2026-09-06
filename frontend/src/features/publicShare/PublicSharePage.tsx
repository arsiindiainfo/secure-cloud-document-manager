/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { isAxiosError } from 'axios';
import { resolveShareLink } from './api';
import { formatBytes } from '../../lib/fileTypes';
import { BrandLogo } from '../../components/BrandLogo';
import { BrandFooter } from '../../components/BrandFooter';
import type { ApiErrorResponse } from '../../types/api';

// §22.11 — no auth, no app shell: a standalone page, not wrapped in the SPA
// header/sidebar/ProtectedRoute the rest of the app uses.
const FAILURE_MESSAGES: Record<string, string> = {
  SHARE_LINK_EXPIRED: 'This link has expired.',
  SHARE_LINK_REVOKED: 'This link has been revoked by its owner.',
  SHARE_LINK_LIMIT_REACHED: 'This link has reached its download limit.',
  SHARE_LINK_NOT_FOUND: 'This link is not valid.',
};

export function PublicSharePage() {
  const { token = '' } = useParams<{ token: string }>();

  const { data, isLoading, error } = useQuery({
    queryKey: ['public-share', token],
    queryFn: () => resolveShareLink(token),
    retry: false,
  });

  let errorMessage: string | null = null;
  if (error) {
    const code = isAxiosError<ApiErrorResponse>(error) ? error.response?.data.error.code : undefined;
    errorMessage = (code && FAILURE_MESSAGES[code]) ?? 'This link is not valid.';
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 p-4 dark:bg-slate-900">
      <div className="w-full max-w-sm rounded-lg bg-white p-8 text-center shadow-lg dark:bg-slate-800">
        <BrandLogo className="mx-auto mb-4 h-8" />
        <p className="mb-6 text-xs font-semibold uppercase tracking-wide text-blue-600 dark:text-blue-400">
          Secure Cloud Document Manager
        </p>

        {isLoading && <div className="mx-auto h-24 w-24 animate-pulse rounded-md bg-slate-200 dark:bg-slate-700" />}

        {errorMessage && <p className="text-sm text-red-600">{errorMessage}</p>}

        {data && (
          <>
            <p className="mb-1 text-lg font-medium text-slate-900 dark:text-slate-100">{data.documentName}</p>
            <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">{formatBytes(data.sizeBytes)}</p>

            {data.downloadUrl ? (
              <a
                href={data.downloadUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-block rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700"
              >
                {data.permission === 'DOWNLOAD' ? 'Download' : 'View'}
              </a>
            ) : (
              <p className="text-sm text-slate-500 dark:text-slate-400">This file has no preview available.</p>
            )}
          </>
        )}

        {/* §31.2 — visible even to unauthenticated recipients of this link. */}
        <p className="mt-6 text-xs text-slate-400 dark:text-slate-500">Secured by Arsi India Info</p>
      </div>
      <BrandFooter />
    </div>
  );
}

