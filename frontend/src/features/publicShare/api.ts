/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import axios from 'axios';
import type { ApiSuccess } from '../../types/api';

// §19 — GET /s/:token is unauthenticated (no Bearer token attached), but
// still lives under the same /api/v1 base as every other endpoint —
// nginx only proxies /api/ to the backend in production, so this must go
// through that same prefix rather than a bare origin-only URL.
const API_BASE = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1';

export interface PublicShareResolution {
  documentName: string;
  sizeBytes: number;
  permission: 'VIEW' | 'DOWNLOAD';
  downloadUrl: string | null;
}

export async function resolveShareLink(token: string): Promise<PublicShareResolution> {
  const { data } = await axios.get<ApiSuccess<PublicShareResolution>>(`${API_BASE}/s/${token}`);
  return data.data;
}

