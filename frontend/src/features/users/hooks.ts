import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as api from './api';
import type { InviteUserPayload, UpdateUserPayload } from './api';

export function usersKey(page: number, limit: number) {
  return ['users', page, limit] as const;
}

export function useUsers(page: number, limit: number) {
  return useQuery({
    queryKey: usersKey(page, limit),
    queryFn: () => api.fetchUsers(page, limit),
  });
}

export function useInviteUser(page: number, limit: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (payload: InviteUserPayload) => api.inviteUser(payload),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: usersKey(page, limit) }),
  });
}

export function useUpdateUser(page: number, limit: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, patch }: { id: number; patch: UpdateUserPayload }) => api.updateUser(id, patch),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: usersKey(page, limit) }),
  });
}
