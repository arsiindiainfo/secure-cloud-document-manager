/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useNavigate } from 'react-router-dom';
import { useAuth } from './useAuth';
import { apiErrorMessage } from '../../lib/apiError';
import { BrandLogo } from '../../components/BrandLogo';
import { BrandFooter } from '../../components/BrandFooter';
import { Recaptcha } from '../../components/Recaptcha';

const RECAPTCHA_CONFIGURED = Boolean(import.meta.env.VITE_RECAPTCHA_SITE_KEY);

// Mirrors the backend's `authLogin` rule group (§5, §23) — the same shape
// is validated client- and server-side.
const loginSchema = z.object({
  email: z.string().min(1, 'Email is required').email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
});

type LoginForm = z.infer<typeof loginSchema>;

export function LoginPage() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [serverError, setServerError] = useState<string | null>(null);
  const [recaptchaToken, setRecaptchaToken] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    setValue,
    formState: { errors, isSubmitting },
  } = useForm<LoginForm>({ resolver: zodResolver(loginSchema) });

  function fillDemoAccount(email: string) {
    setValue('email', email, { shouldValidate: true });
    setValue('password', 'Passw0rd!', { shouldValidate: true });
  }

  async function onSubmit(values: LoginForm) {
    setServerError(null);
    try {
      await login(values.email, values.password, recaptchaToken);
      navigate('/', { replace: true });
    } catch (err) {
      setServerError(apiErrorMessage(err, 'Invalid email or password'));
    }
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 dark:bg-slate-900">
      <div className="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <BrandLogo className="mb-4 h-10" />
        <h1 className="mb-1 text-xl font-semibold text-slate-900 dark:text-slate-100">
          Secure Cloud Document Manager
        </h1>
        <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">Sign in to your workspace</p>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <div>
            <label htmlFor="email" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Email <span aria-hidden>*</span>
            </label>
            <input
              id="email"
              type="email"
              autoComplete="username"
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              {...register('email')}
            />
            {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email.message}</p>}
          </div>

          <div>
            <label htmlFor="password" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
              Password <span aria-hidden>*</span>
            </label>
            <input
              id="password"
              type="password"
              autoComplete="current-password"
              className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
              {...register('password')}
            />
            {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password.message}</p>}
          </div>

          <Recaptcha onChange={setRecaptchaToken} />

          {serverError && (
            <p role="alert" className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
              {serverError}
            </p>
          )}

          <button
            type="submit"
            disabled={isSubmitting || (RECAPTCHA_CONFIGURED && !recaptchaToken)}
            className="w-full rounded-md bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-60"
          >
            {isSubmitting ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <div className="mt-6 border-t border-slate-200 pt-4 dark:border-slate-700">
          <p className="mb-2 text-xs font-medium uppercase tracking-wide text-slate-400">Demo accounts</p>
          <div className="flex flex-col gap-1.5">
            {[
              { label: 'Ava Admin', role: 'ADMIN', email: 'admin@meridian.test' },
              { label: 'Mark Manager', role: 'MANAGER', email: 'manager@meridian.test' },
              { label: 'Eve Employee', role: 'EMPLOYEE', email: 'employee@meridian.test' },
            ].map((account) => (
              <button
                key={account.email}
                type="button"
                onClick={() => fillDemoAccount(account.email)}
                className="flex items-center justify-between rounded-md border border-slate-200 px-3 py-1.5 text-left text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700/50"
              >
                <span>{account.label}</span>
                <span className="text-xs text-slate-400">{account.role}</span>
              </button>
            ))}
          </div>
          <p className="mt-2 text-xs text-slate-400 dark:text-slate-500">Click a name to fill in its credentials, then Sign in.</p>
        </div>
      </div>
      <BrandFooter />
    </div>
  );
}

