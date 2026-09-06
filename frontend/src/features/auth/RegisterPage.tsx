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
import { Link } from 'react-router-dom';
import { register as registerAccount } from './api';
import { apiErrorMessage } from '../../lib/apiError';
import { BrandLogo } from '../../components/BrandLogo';
import { BrandFooter } from '../../components/BrandFooter';
import { Recaptcha } from '../../components/Recaptcha';

const RECAPTCHA_CONFIGURED = Boolean(import.meta.env.VITE_RECAPTCHA_SITE_KEY);

// Mirrors the backend's `authRegister` rule group (§5, §15).
const registerSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters').max(120),
  email: z.string().min(1, 'Email is required').email('Enter a valid email address'),
  password: z.string().min(8, 'Password must be at least 8 characters'),
});

type RegisterForm = z.infer<typeof registerSchema>;

export function RegisterPage() {
  const [serverError, setServerError] = useState<string | null>(null);
  const [recaptchaToken, setRecaptchaToken] = useState<string | null>(null);
  const [registered, setRegistered] = useState<string | null>(null);

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<RegisterForm>({ resolver: zodResolver(registerSchema) });

  async function onSubmit(values: RegisterForm) {
    setServerError(null);
    try {
      await registerAccount({ ...values, recaptchaToken });
      setRegistered(values.email);
    } catch (err) {
      setServerError(apiErrorMessage(err, 'Could not create your account'));
    }
  }

  return (
    <div className="flex min-h-screen flex-col items-center justify-center bg-slate-50 px-4 dark:bg-slate-900">
      <div className="w-full max-w-sm rounded-lg border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-700 dark:bg-slate-800">
        <BrandLogo className="mb-4 h-10" />

        {registered ? (
          <>
            <h1 className="mb-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Check your email</h1>
            <p className="text-sm text-slate-500 dark:text-slate-400">
              We sent a verification link to <span className="font-medium text-slate-700 dark:text-slate-300">{registered}</span>.
              Click it to activate your account, then sign in.
            </p>
            <Link
              to="/login"
              className="mt-6 inline-block w-full rounded-md bg-blue-600 px-3 py-2 text-center text-sm font-medium text-white hover:bg-blue-700"
            >
              Back to sign in
            </Link>
          </>
        ) : (
          <>
            <h1 className="mb-1 text-xl font-semibold text-slate-900 dark:text-slate-100">Create your account</h1>
            <p className="mb-6 text-sm text-slate-500 dark:text-slate-400">Secure Cloud Document Manager</p>

            <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
              <div>
                <label htmlFor="name" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">
                  Name <span aria-hidden>*</span>
                </label>
                <input
                  id="name"
                  autoComplete="name"
                  className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                  {...register('name')}
                />
                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name.message}</p>}
              </div>

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
                  autoComplete="new-password"
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
                {isSubmitting ? 'Creating account…' : 'Create account'}
              </button>
            </form>

            <p className="mt-4 text-center text-sm text-slate-500 dark:text-slate-400">
              Already have an account?{' '}
              <Link to="/login" className="font-medium text-blue-600 hover:underline dark:text-blue-400">
                Sign in
              </Link>
            </p>
          </>
        )}
      </div>
      <BrandFooter />
    </div>
  );
}
