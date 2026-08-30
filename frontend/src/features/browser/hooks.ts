import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import * as api from './api';

export function folderChildrenKey(folderId: number | null) {
  return ['folder', folderId ?? 'root', 'children'] as const;
}

export function useFolderChildren(folderId: number | null) {
  return useQuery({
    queryKey: folderChildrenKey(folderId),
    queryFn: () => api.fetchFolderChildren(folderId),
  });
}

export function useCreateFolder(parentFolderId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (name: string) => api.createFolder(name, parentFolderId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: folderChildrenKey(parentFolderId) }),
  });
}

export function useRenameFolder(parentFolderId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: ({ id, name }: { id: number; name: string }) => api.renameFolder(id, name),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: folderChildrenKey(parentFolderId) }),
  });
}

export function useDeleteFolder(parentFolderId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.deleteFolder(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: folderChildrenKey(parentFolderId) }),
  });
}

export function useRestoreFolder(parentFolderId: number | null) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: (id: number) => api.restoreFolder(id),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: folderChildrenKey(parentFolderId) }),
  });
}
