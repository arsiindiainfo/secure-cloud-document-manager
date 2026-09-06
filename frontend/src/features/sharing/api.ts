/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { apiClient } from '../../lib/apiClient';
import type { ApiSuccess, Permission } from '../../types/api';

/** Sharing is scoped to either a document or a folder — same grant shape, different endpoint. */
export type ShareTarget = { type: 'document'; id: number } | { type: 'folder'; id: number };

function permissionsPath(target: ShareTarget): string {
  return target.type === 'document' ? `/documents/${target.id}/permissions` : `/folders/${target.id}/permissions`;
}

export interface PermissionGrant {
  userId: number;
  name: string;
  email: string;
  permission: Permission;
  grantedAt: string;
}

export async function fetchGrants(target: ShareTarget): Promise<PermissionGrant[]> {
  const { data } = await apiClient.get<ApiSuccess<PermissionGrant[]>>(permissionsPath(target));
  return data.data;
}

export async function grantAccess(target: ShareTarget, email: string, permission: Permission): Promise<PermissionGrant> {
  const { data } = await apiClient.post<ApiSuccess<PermissionGrant>>(permissionsPath(target), { email, permission });
  return data.data;
}

export async function revokeAccess(target: ShareTarget, userId: number): Promise<void> {
  await apiClient.delete(`${permissionsPath(target)}/${userId}`);
}

export interface UserSearchResult {
  id: number;
  name: string;
  email: string;
}

export async function searchUsers(query: string): Promise<UserSearchResult[]> {
  const q = query.trim();
  if (q.length < 2) return [];
  const { data } = await apiClient.get<ApiSuccess<UserSearchResult[]>>('/users/search', { params: { q } });
  return data.data;
}

export type SharePermission = 'VIEW' | 'DOWNLOAD';

export interface ShareLink {
  id: number;
  documentId: number;
  permission: SharePermission;
  maxDownloads: number | null;
  downloadCount: number;
  expiresAt: string;
  revokedAt: string | null;
  createdBy: number;
  createdAt: string;
}

export interface ShareLinkCreated {
  id: number;
  url: string;
  expiresAt: string;
}

export async function fetchShareLinks(documentId: number): Promise<ShareLink[]> {
  const { data } = await apiClient.get<ApiSuccess<ShareLink[]>>(`/documents/${documentId}/share-links`);
  return data.data;
}

export async function createShareLink(
  documentId: number,
  permission: SharePermission,
  expiresInHours: number,
  maxDownloads?: number,
): Promise<ShareLinkCreated> {
  const { data } = await apiClient.post<ApiSuccess<ShareLinkCreated>>(`/documents/${documentId}/share-links`, {
    permission,
    expiresInHours,
    maxDownloads,
  });
  return data.data;
}

export async function revokeShareLink(id: number): Promise<void> {
  await apiClient.delete(`/share-links/${id}`);
}

