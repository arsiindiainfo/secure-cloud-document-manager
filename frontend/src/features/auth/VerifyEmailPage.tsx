/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useParams, Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { verifyEmail } from './api';
import { apiErrorMessage } from '../../lib/apiError';
import { BrandLogo } from '../../components/BrandLogo';
import { BrandFooter } from '../../components/BrandFooter';

/** No auth, no app shell — reached straight from the link in the verification email. */
export function VerifyEmailPage() {
  const { token = '' } = useParams<{ token: string }>();

  const { isLoading, isError, error } = useQuery({
    queryKey: ['verify-email', token],
    queryFn: () => verifyEmail(token),
    retry: false,
  });

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 dark:bg-slate-900">
      <div className="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <BrandLogo className="mx-auto mb-4 h-10" />

        {isLoading && <p className="text-sm text-slate-500 dark:text-slate-400">Verifying…</p>}

        {isError && (
          <>
            <h1 className="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">Verification failed</h1>
            <p className="text-sm text-red-600">{apiErrorMessage(error, 'This link is invalid or has already been used.')}</p>
          </>
        )}

        {!isLoading && !isError && (
          <>
            <h1 className="mb-2 text-lg font-semibold text-slate-900 dark:text-slate-100">Email verified</h1>
            <p className="text-sm text-slate-500 dark:text-slate-400">Your account is ready — you can sign in now.</p>
          </>
        )}

        <Link
          to="/login"
          className="mt-6 inline-block w-full rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700"
        >
          Go to sign in
        </Link>
      </div>
      <BrandFooter />
    </div>
  );
}
