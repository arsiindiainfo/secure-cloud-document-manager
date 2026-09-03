/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { apiClient } from '../../lib/apiClient';
import type { ApiSuccess, Permission } from '../../types/api';

export interface PermissionGrant {
  userId: number;
  name: string;
  email: string;
  permission: Permission;
  grantedAt: string;
}

export async function fetchGrants(documentId: number): Promise<PermissionGrant[]> {
  const { data } = await apiClient.get<ApiSuccess<PermissionGrant[]>>(`/documents/${documentId}/permissions`);
  return data.data;
}

export async function grantAccess(documentId: number, email: string, permission: Permission): Promise<PermissionGrant> {
  const { data } = await apiClient.post<ApiSuccess<PermissionGrant>>(`/documents/${documentId}/permissions`, { email, permission });
  return data.data;
}

export async function revokeAccess(documentId: number, userId: number): Promise<void> {
  await apiClient.delete(`/documents/${documentId}/permissions/${userId}`);
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

