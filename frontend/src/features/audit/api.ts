/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { apiClient } from '../../lib/apiClient';
import type { ApiPaginatedSuccess, AuditLogEntry } from '../../types/api';

export interface AuditLogFilters {
  action?: string;
  entityType?: string;
  userId?: number;
  dateFrom?: string;
  dateTo?: string;
}

export async function fetchAuditLogs(filters: AuditLogFilters): Promise<{ items: AuditLogEntry[]; total: number }> {
  const { data } = await apiClient.get<ApiPaginatedSuccess<AuditLogEntry>>('/audit-logs', { params: filters });
  return { items: data.data, total: data.meta.total };
}

