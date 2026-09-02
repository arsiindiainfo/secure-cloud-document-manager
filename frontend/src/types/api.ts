// Mirrors the backend's response/error envelope exactly (§13) — every API
// call in the app is typed against one of these three shapes, never a raw
// unwrapped payload.

export interface ApiFieldError {
  field: string;
  message: string;
}

export interface ApiErrorBody {
  code: string;
  message: string;
  details?: ApiFieldError[];
}

export interface ApiSuccess<T> {
  success: true;
  data: T;
}

export interface ApiPaginatedSuccess<T> {
  success: true;
  data: T[];
  meta: {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
  };
}

export interface ApiErrorResponse {
  success: false;
  error: ApiErrorBody;
}

export type Role = 'ADMIN' | 'MANAGER' | 'EMPLOYEE';
export type UserStatus = 'ACTIVE' | 'DISABLED';

export interface User {
  id: number;
  name: string;
  email: string;
  role: Role;
  status: UserStatus;
}

export interface Folder {
  id: number;
  parentFolderId: number | null;
  name: string;
  createdBy: number;
  createdAt: string;
  updatedAt: string;
  deletedAt: string | null;
}

export interface Document {
  id: number;
  folderId: number;
  name: string;
  description: string | null;
  tags: string[];
  currentVersion: number;
  createdBy: number;
  createdAt: string;
  updatedAt: string;
  deletedAt: string | null;
}

export interface DocumentVersion {
  id: number;
  documentId: number;
  versionNo: number;
  mimeType: string;
  sizeBytes: number;
  checksumSha256: string;
  hasThumbnail: boolean;
  isCurrent: boolean;
  uploadedBy: number;
  uploadedAt: string;
}

export interface BreadcrumbEntry {
  id: number;
  name: string;
}

export interface FolderChildren {
  folders: Folder[];
  documents: Document[];
  breadcrumb: BreadcrumbEntry[];
}

export type Permission = 'VIEWER' | 'EDITOR' | 'OWNER';

export type ProcessingStatus = 'PENDING' | 'PROCESSING' | 'COMPLETED' | 'FAILED';

export interface DocumentDetail extends Document {
  currentVersionDetail: DocumentVersion | null;
  effectivePermission: Permission;
  processingStatus: ProcessingStatus | null;
}

export interface DocumentSearchResult {
  id: number;
  name: string;
  description: string | null;
  tags: string[];
  folderId: number;
  folderName: string;
  currentVersion: number;
  mimeType: string;
  sizeBytes: number;
  hasThumbnail: boolean;
  createdBy: number;
  createdAt: string;
  updatedAt: string;
}

export interface TrashedFolder extends Folder {
  daysRemaining: number;
}

export interface TrashedDocument extends Document {
  daysRemaining: number;
}

export interface TrashListing {
  folders: TrashedFolder[];
  documents: TrashedDocument[];
}

export interface RecentDocumentActivity {
  documentId: number;
  name: string;
  folderId: number;
  action: string;
  at: string;
}

export interface SharedFolderSummary {
  id: number;
  name: string;
}

export interface DashboardSummary {
  recentDocuments: RecentDocumentActivity[];
  storageUsedBytes: number;
  sharedFolders: SharedFolderSummary[];
}

export interface AuditLogEntry {
  id: number;
  userId: number | null;
  action: string;
  entityType: 'DOCUMENT' | 'FOLDER' | 'USER' | 'SHARE_LINK';
  entityId: number;
  details: Record<string, unknown> | null;
  createdAt: string;
}
