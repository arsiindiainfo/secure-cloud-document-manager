/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { apiClient } from '../../lib/apiClient';
import type { ApiPaginatedSuccess, ApiSuccess, Role, User, UserStatus } from '../../types/api';

export interface UsersPage {
  items: User[];
  total: number;
  totalPages: number;
}

export async function fetchUsers(page: number, limit: number): Promise<UsersPage> {
  const { data } = await apiClient.get<ApiPaginatedSuccess<User>>('/users', { params: { page, limit } });
  return { items: data.data, total: data.meta.total, totalPages: data.meta.totalPages };
}

export interface InviteUserPayload {
  name: string;
  email: string;
  role: Role;
}

// No password field — the server generates one and emails it to the invitee.
export async function inviteUser(payload: InviteUserPayload): Promise<User> {
  const { data } = await apiClient.post<ApiSuccess<User>>('/users', payload);
  return data.data;
}

export interface UpdateUserPayload {
  role?: Role;
  status?: UserStatus;
}

export async function updateUser(id: number, patch: UpdateUserPayload): Promise<User> {
  const { data } = await apiClient.put<ApiSuccess<User>>(`/users/${id}`, patch);
  return data.data;
}

