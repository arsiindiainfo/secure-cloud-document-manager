/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { DocumentDetailPanel } from './DocumentDetailPanel';
import * as api from './api';
import type { DocumentDetail } from '../../types/api';

// The panel fetches via api.fetchDocument on mount (and would fetch a
// preview/download URL if those tabs/actions were exercised, which this test
// never touches since it stays on the default "details" tab).
vi.mock('./api');

const BASE_DETAIL: DocumentDetail = {
  id: 1,
  folderId: 10,
  name: 'Report.pdf',
  description: null,
  tags: [],
  currentVersion: 1,
  createdBy: 1,
  createdAt: '2026-01-01T00:00:00Z',
  updatedAt: '2026-01-01T00:00:00Z',
  deletedAt: null,
  currentVersionDetail: null,
  effectivePermission: 'VIEWER',
  processingStatus: null,
};

function renderPanel(detail: DocumentDetail) {
  vi.mocked(api.fetchDocument).mockResolvedValue(detail);
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <DocumentDetailPanel documentId={detail.id} folderId={detail.folderId} onClose={() => {}} />
    </QueryClientProvider>,
  );
}

describe('DocumentDetailPanel permission-aware rendering', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('hides Edit and Share for a VIEWER', async () => {
    renderPanel({ ...BASE_DETAIL, effectivePermission: 'VIEWER' });

    await waitFor(() => expect(screen.getByText('Report.pdf')).toBeInTheDocument());

    expect(screen.queryByText('Edit')).not.toBeInTheDocument();
    expect(screen.queryByText('Share')).not.toBeInTheDocument();
  });

  it('shows Edit and Share for an OWNER', async () => {
    renderPanel({ ...BASE_DETAIL, effectivePermission: 'OWNER' });

    await waitFor(() => expect(screen.getByText('Report.pdf')).toBeInTheDocument());

    expect(screen.getByText('Edit')).toBeInTheDocument();
    expect(screen.getByText('Share')).toBeInTheDocument();
  });
});

