/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { apiClient } from '../../lib/apiClient';
import type { ApiSuccess, Folder, FolderChildren } from '../../types/api';

export async function fetchFolderChildren(folderId: number | null): Promise<FolderChildren> {
  const path = folderId === null ? '/folders/children' : `/folders/${folderId}/children`;
  const { data } = await apiClient.get<ApiSuccess<FolderChildren>>(path);
  return data.data;
}

export async function createFolder(name: string, parentFolderId: number | null): Promise<Folder> {
  const { data } = await apiClient.post<ApiSuccess<Folder>>('/folders', { name, parentFolderId });
  return data.data;
}

export async function renameFolder(id: number, name: string): Promise<Folder> {
  const { data } = await apiClient.put<ApiSuccess<Folder>>(`/folders/${id}`, { name });
  return data.data;
}

export async function moveFolder(id: number, name: string, parentFolderId: number | null): Promise<Folder> {
  const { data } = await apiClient.put<ApiSuccess<Folder>>(`/folders/${id}`, { name, parentFolderId });
  return data.data;
}

export async function deleteFolder(id: number): Promise<void> {
  await apiClient.delete(`/folders/${id}`);
}

export async function restoreFolder(id: number): Promise<void> {
  await apiClient.post(`/folders/${id}/restore`);
}

