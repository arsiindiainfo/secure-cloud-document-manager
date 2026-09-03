/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { isAxiosError } from 'axios';
import type { ApiErrorResponse } from '../types/api';

export function apiErrorMessage(err: unknown, fallback = 'Something went wrong.'): string {
  if (isAxiosError<ApiErrorResponse>(err)) {
    return err.response?.data.error.message ?? fallback;
  }
  return fallback;
}

