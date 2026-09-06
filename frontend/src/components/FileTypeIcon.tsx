/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { File, FileArchive, FileImage, FileSpreadsheet, FileText, Presentation, type LucideIcon } from 'lucide-react';

const ICON_BY_MIME_TYPE: Record<string, { icon: LucideIcon; className: string }> = {
  'application/pdf': { icon: FileText, className: 'text-red-500' },
  'application/msword': { icon: FileText, className: 'text-blue-500' },
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document': { icon: FileText, className: 'text-blue-500' },
  'application/vnd.ms-excel': { icon: FileSpreadsheet, className: 'text-green-600' },
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': { icon: FileSpreadsheet, className: 'text-green-600' },
  'text/csv': { icon: FileSpreadsheet, className: 'text-green-600' },
  'application/vnd.ms-powerpoint': { icon: Presentation, className: 'text-orange-500' },
  'application/vnd.openxmlformats-officedocument.presentationml.presentation': { icon: Presentation, className: 'text-orange-500' },
  'text/plain': { icon: FileText, className: 'text-slate-500' },
  'application/rtf': { icon: FileText, className: 'text-slate-500' },
  'image/png': { icon: FileImage, className: 'text-purple-500' },
  'image/jpeg': { icon: FileImage, className: 'text-purple-500' },
  'image/gif': { icon: FileImage, className: 'text-purple-500' },
  'image/webp': { icon: FileImage, className: 'text-purple-500' },
  'application/zip': { icon: FileArchive, className: 'text-amber-600' },
};

interface FileTypeIconProps {
  mimeType: string | null;
  size?: number;
}

/** Per-file-type icon for listings — falls back to a generic file icon for anything unmapped. */
export function FileTypeIcon({ mimeType, size = 18 }: FileTypeIconProps) {
  const match = mimeType !== null ? ICON_BY_MIME_TYPE[mimeType] : undefined;
  const Icon = match?.icon ?? File;

  return <Icon size={size} className={`shrink-0 ${match?.className ?? 'text-slate-400'}`} aria-hidden />;
}
