import { isAxiosError } from 'axios';
import type { ApiErrorResponse } from '../types/api';

export function apiErrorMessage(err: unknown, fallback = 'Something went wrong.'): string {
  if (isAxiosError<ApiErrorResponse>(err)) {
    return err.response?.data.error.message ?? fallback;
  }
  return fallback;
}
