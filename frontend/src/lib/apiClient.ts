/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import axios, { type AxiosRequestConfig } from 'axios';
import { tokenStore } from './tokenStore';
import type { ApiSuccess } from '../types/api';

// §21.1 — attaches Bearer token, refreshes on 401. Every feature hook
// (useDocuments, useFolder, ...) goes through this one instance.
export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1',
});

apiClient.interceptors.request.use((config) => {
  const token = tokenStore.getAccessToken();
  if (token) {
    config.headers.set('Authorization', `Bearer ${token}`);
  }
  return config;
});

interface RetryableConfig extends AxiosRequestConfig {
  _retried?: boolean;
}

let inFlightRefresh: Promise<string | null> | null = null;

async function refreshAccessToken(): Promise<string | null> {
  const refreshToken = tokenStore.getRefreshToken();
  if (!refreshToken) return null;

  try {
    const { data } = await axios.post<ApiSuccess<{ accessToken: string; refreshToken: string }>>(
      `${apiClient.defaults.baseURL}/auth/refresh`,
      { refreshToken },
    );
    tokenStore.setTokens(data.data.accessToken, data.data.refreshToken);
    return data.data.accessToken;
  } catch {
    tokenStore.clear();
    return null;
  }
}

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const config = error.config as RetryableConfig | undefined;

    if (error.response?.status === 401 && config && !config._retried && !config.url?.includes('/auth/')) {
      config._retried = true;
      inFlightRefresh ??= refreshAccessToken();
      const newToken = await inFlightRefresh;
      inFlightRefresh = null;

      if (newToken) {
        config.headers = { ...config.headers, Authorization: `Bearer ${newToken}` };
        return apiClient(config);
      }

      tokenStore.clear();
    }

    return Promise.reject(error);
  },
);

