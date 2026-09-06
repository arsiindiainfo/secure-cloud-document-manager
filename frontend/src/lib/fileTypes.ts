/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

// Mirrors backend/app/Constants/FileTypes.php — kept in sync manually since
// the two run in different languages. Used only for the fast client-side
// rejection (§22.6); the backend is still the source of truth and
// re-validates independently.
export const ALLOWED_MIME_TYPES = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'application/vnd.ms-powerpoint',
  'application/vnd.openxmlformats-officedocument.presentationml.presentation',
  'text/csv',
  'text/plain',
  'application/rtf',
  'image/png',
  'image/jpeg',
  'image/gif',
  'image/webp',
  'application/zip',
];

export const MAX_UPLOAD_SIZE_BYTES = 25 * 1024 * 1024;

// Human-readable — shown next to the upload control so a rejected file
// (§22.6's "File type not supported") isn't a dead end.
export const ALLOWED_EXTENSIONS_LABEL =
  'PDF, Word, Excel, PowerPoint, CSV, TXT, RTF, PNG, JPG, GIF, WebP, ZIP';

export function formatBytes(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`;
  const units = ['KB', 'MB', 'GB'];
  let value = bytes / 1024;
  let unitIndex = 0;
  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024;
    unitIndex += 1;
  }
  return `${value.toFixed(1)} ${units[unitIndex]}`;
}

