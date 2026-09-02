import { describe, it, expect, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import type { ReactNode } from 'react';
import { useDocumentUpload } from './useDocumentUpload';
import * as api from './api';
import * as uploadLib from '../../lib/uploadWithProgress';
import type { InitiateUploadResult } from './api';
import type { Document } from '../../types/api';

// The hook's three-step orchestration (initiate -> PUT to S3 -> complete)
// talks to real network/crypto through these two modules — mock both so the
// test drives the state machine deterministically instead of hitting them.
vi.mock('./api');
vi.mock('../../lib/uploadWithProgress');

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((res) => {
    resolve = res;
  });
  return { promise, resolve };
}

function createWrapper() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return function Wrapper({ children }: { children: ReactNode }) {
    return <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>;
  };
}

const FAKE_DOCUMENT: Document = {
  id: 1,
  folderId: 10,
  name: 'file.pdf',
  description: null,
  tags: [],
  currentVersion: 1,
  createdBy: 1,
  createdAt: '2026-01-01T00:00:00Z',
  updatedAt: '2026-01-01T00:00:00Z',
  deletedAt: null,
};

describe('useDocumentUpload', () => {
  beforeEach(() => {
    vi.resetAllMocks();
  });

  it('drives a valid file through pending -> uploading -> processing -> done', async () => {
    const initiate = deferred<InitiateUploadResult>();
    const upload = deferred<void>();
    const sha = deferred<string>();
    const complete = deferred<Document>();

    vi.mocked(api.initiateUpload).mockReturnValue(initiate.promise);
    vi.mocked(uploadLib.uploadWithProgress).mockReturnValue(upload.promise);
    vi.mocked(uploadLib.sha256Hex).mockReturnValue(sha.promise);
    vi.mocked(api.completeUpload).mockReturnValue(complete.promise);

    const { result } = renderHook(() => useDocumentUpload(10), { wrapper: createWrapper() });
    const file = new File(['hello'], 'file.pdf', { type: 'application/pdf' });

    act(() => {
      void result.current.uploadFiles([file]);
    });
    await waitFor(() => expect(result.current.items[0]?.status).toBe('pending'));

    initiate.resolve({ uploadUrl: 'https://s3.example/upload', s3Key: 'key-1', expiresIn: 900 });
    await waitFor(() => expect(result.current.items[0]?.status).toBe('uploading'));

    upload.resolve();
    await waitFor(() => expect(result.current.items[0]?.status).toBe('processing'));

    sha.resolve('deadbeef');
    complete.resolve(FAKE_DOCUMENT);
    await waitFor(() => expect(result.current.items[0]?.status).toBe('done'));

    expect(api.initiateUpload).toHaveBeenCalledWith(10, 'file.pdf', 'application/pdf', file.size);
    expect(api.completeUpload).toHaveBeenCalledWith(10, 'key-1', 'file.pdf', 'deadbeef');
  });

  it('rejects an oversized file without ever calling initiateUpload', async () => {
    const { result } = renderHook(() => useDocumentUpload(10), { wrapper: createWrapper() });
    const oversizedFile = new File([new Uint8Array(26 * 1024 * 1024)], 'big.pdf', { type: 'application/pdf' });

    await act(async () => {
      await result.current.uploadFiles([oversizedFile]);
    });

    expect(result.current.items[0]?.status).toBe('error');
    expect(api.initiateUpload).not.toHaveBeenCalled();
  });

  it('rejects an unsupported mime type without ever calling initiateUpload', async () => {
    const { result } = renderHook(() => useDocumentUpload(10), { wrapper: createWrapper() });
    const wrongTypeFile = new File(['x'], 'virus.exe', { type: 'application/x-msdownload' });

    await act(async () => {
      await result.current.uploadFiles([wrongTypeFile]);
    });

    expect(result.current.items[0]?.status).toBe('error');
    expect(api.initiateUpload).not.toHaveBeenCalled();
  });
});
