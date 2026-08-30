import { apiClient } from '../../lib/apiClient';
import type { ApiSuccess, Document, DocumentDetail, DocumentVersion } from '../../types/api';

export interface InitiateUploadResult {
  uploadUrl: string;
  s3Key: string;
  expiresIn: number;
}

export async function initiateUpload(
  folderId: number,
  fileName: string,
  mimeType: string,
  sizeBytes: number,
): Promise<InitiateUploadResult> {
  const { data } = await apiClient.post<ApiSuccess<InitiateUploadResult>>('/documents/uploads/initiate', {
    folderId,
    fileName,
    mimeType,
    sizeBytes,
  });
  return data.data;
}

export async function completeUpload(
  folderId: number,
  s3Key: string,
  name: string,
  checksumSha256: string,
): Promise<Document> {
  const { data } = await apiClient.post<ApiSuccess<Document>>('/documents/uploads/complete', {
    folderId,
    s3Key,
    name,
    checksumSha256,
  });
  return data.data;
}

export async function initiateVersionUpload(
  documentId: number,
  fileName: string,
  mimeType: string,
  sizeBytes: number,
): Promise<InitiateUploadResult> {
  const { data } = await apiClient.post<ApiSuccess<InitiateUploadResult>>(`/documents/${documentId}/versions/initiate`, {
    fileName,
    mimeType,
    sizeBytes,
  });
  return data.data;
}

export async function completeVersionUpload(documentId: number, s3Key: string, checksumSha256: string): Promise<void> {
  await apiClient.post(`/documents/${documentId}/versions/complete`, { s3Key, checksumSha256 });
}

export async function fetchDocument(id: number): Promise<DocumentDetail> {
  const { data } = await apiClient.get<ApiSuccess<DocumentDetail>>(`/documents/${id}`);
  return data.data;
}

export async function updateDocument(
  id: number,
  patch: Partial<{ name: string; description: string; tags: string; folderId: number }>,
): Promise<Document> {
  const { data } = await apiClient.put<ApiSuccess<Document>>(`/documents/${id}`, patch);
  return data.data;
}

export async function deleteDocument(id: number): Promise<void> {
  await apiClient.delete(`/documents/${id}`);
}

export async function restoreDocument(id: number): Promise<void> {
  await apiClient.post(`/documents/${id}/restore`);
}

export async function fetchVersions(documentId: number): Promise<DocumentVersion[]> {
  const { data } = await apiClient.get<ApiSuccess<DocumentVersion[]>>(`/documents/${documentId}/versions`);
  return data.data;
}
