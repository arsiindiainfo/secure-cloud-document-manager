import { apiClient } from '../../lib/apiClient';
import type { ApiSuccess, TrashListing } from '../../types/api';

export async function fetchTrash(): Promise<TrashListing> {
  const { data } = await apiClient.get<ApiSuccess<TrashListing>>('/trash');
  return data.data;
}
