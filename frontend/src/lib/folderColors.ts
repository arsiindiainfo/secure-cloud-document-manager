/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

// Pastel-background + saturated-icon pairs, cycled by folder id so the same
// folder always lands on the same color across renders/sessions.
const FOLDER_COLORS = [
  'bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400',
  'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400',
  'bg-purple-100 text-purple-600 dark:bg-purple-950 dark:text-purple-400',
  'bg-amber-100 text-amber-600 dark:bg-amber-950 dark:text-amber-400',
  'bg-sky-100 text-sky-600 dark:bg-sky-950 dark:text-sky-400',
  'bg-pink-100 text-pink-600 dark:bg-pink-950 dark:text-pink-400',
];

export function folderColorFor(folderId: number): string {
  return FOLDER_COLORS[folderId % FOLDER_COLORS.length];
}
