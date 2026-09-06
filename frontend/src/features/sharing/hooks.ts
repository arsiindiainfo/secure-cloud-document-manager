/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as api from './api';
import type { ShareTarget } from './api';
import type { Permission } from '../../types/api';

export function grantsKey(target: ShareTarget) {
  return ['share', target.type, target.id, 'permissions'] as const;
}
export function shareLinksKey(documentId: number) {
  return ['document', documentId, 'share-links'] as const;
}

export function useGrants(target: ShareTarget) {
  return useQuery({ queryKey: grantsKey(target), queryFn: () => api.fetchGrants(target) });
}

export function useGrantAccess(target: ShareTarget) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ email, permission }: { email: string; permission: Permission }) => api.grantAccess(target, email, permission),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: grantsKey(target) }),
  });
}

export function useRevokeAccess(target: ShareTarget) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (userId: number) => api.revokeAccess(target, userId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: grantsKey(target) }),
  });
}

export function useShareLinks(documentId: number) {
  return useQuery({ queryKey: shareLinksKey(documentId), queryFn: () => api.fetchShareLinks(documentId) });
}

export function useCreateShareLink(documentId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ permission, expiresInHours, maxDownloads }: { permission: api.SharePermission; expiresInHours: number; maxDownloads?: number }) =>
      api.createShareLink(documentId, permission, expiresInHours, maxDownloads),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: shareLinksKey(documentId) }),
  });
}

export function useRevokeShareLink(documentId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.revokeShareLink(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: shareLinksKey(documentId) }),
  });
}

export function useUserSearch(query: string) {
  return useQuery({
    queryKey: ['user-search', query],
    queryFn: () => api.searchUsers(query),
    enabled: query.trim().length >= 2,
  });
}
