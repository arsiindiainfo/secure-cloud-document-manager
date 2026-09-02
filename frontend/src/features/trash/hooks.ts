import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchTrash } from './api';
import { restoreFolder } from '../browser/api';
import { restoreDocument } from '../documents/api';

export const trashKey = ['trash'] as const;

export function useTrash() {
  return useQuery({
    queryKey: trashKey,
    queryFn: fetchTrash,
  });
}

export function useRestoreTrashedFolder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => restoreFolder(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: trashKey }),
  });
}

export function useRestoreTrashedDocument() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => restoreDocument(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: trashKey }),
  });
}
