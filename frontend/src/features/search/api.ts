import { apiClient } from '../../lib/apiClient';
import type { ApiPaginatedSuccess, DocumentSearchResult } from '../../types/api';

export interface SearchResults {
  items: DocumentSearchResult[];
  total: number;
}

export async function searchDocuments(query: string): Promise<SearchResults> {
  const { data } = await apiClient.get<ApiPaginatedSuccess<DocumentSearchResult>>('/documents', {
    params: { search: query },
  });
  return { items: data.data, total: data.meta.total };
}
