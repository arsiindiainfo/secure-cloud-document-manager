/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { apiClient } from '../../lib/apiClient';
import type { ApiSuccess } from '../../types/api';

export interface RegisterPayload {
  name: string;
  email: string;
  password: string;
  recaptchaToken?: string | null;
}

// Neither call establishes a session (no tokens come back) — registering
// only creates the account, and verifying only unlocks it; the person
// still signs in normally afterward through AuthContext's login().
export async function register(payload: RegisterPayload): Promise<{ userId: number }> {
  const { data } = await apiClient.post<ApiSuccess<{ userId: number }>>('/auth/register', payload);
  return data.data;
}

export async function verifyEmail(token: string): Promise<void> {
  await apiClient.post('/auth/verify-email', { token });
}
