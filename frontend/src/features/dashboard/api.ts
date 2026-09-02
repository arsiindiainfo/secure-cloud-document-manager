import { apiClient } from '../../lib/apiClient';
import type { ApiSuccess, DashboardSummary } from '../../types/api';

export async function fetchDashboardSummary(): Promise<DashboardSummary> {
  const { data } = await apiClient.get<ApiSuccess<DashboardSummary>>('/dashboard');
  return data.data;
}
