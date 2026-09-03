/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

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

