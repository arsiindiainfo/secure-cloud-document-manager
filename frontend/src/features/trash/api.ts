/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { apiClient } from '../../lib/apiClient';
import type { ApiSuccess, TrashListing } from '../../types/api';

export async function fetchTrash(): Promise<TrashListing> {
  const { data } = await apiClient.get<ApiSuccess<TrashListing>>('/trash');
  return data.data;
}

