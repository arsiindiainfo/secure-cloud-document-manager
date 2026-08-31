import axios from 'axios';
import type { ApiSuccess } from '../../types/api';

// §19 — GET /s/:token is unversioned and unauthenticated, unlike every other
// route (which goes through apiClient's /api/v1 base + Bearer token). This
// derives the API's origin from the same env var apiClient uses, stripping
// the /api/v1 suffix, so both clients stay pointed at the same server.
const API_BASE = import.meta.env.VITE_API_BASE_URL ?? 'http://localhost:8080/api/v1';
const API_ORIGIN = API_BASE.replace(/\/api\/v1\/?$/, '');

export interface PublicShareResolution {
  documentName: string;
  sizeBytes: number;
  permission: 'VIEW' | 'DOWNLOAD';
  downloadUrl: string | null;
}

export async function resolveShareLink(token: string): Promise<PublicShareResolution> {
  const { data } = await axios.get<ApiSuccess<PublicShareResolution>>(`${API_ORIGIN}/s/${token}`);
  return data.data;
}
