/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { createBrowserRouter, Navigate } from 'react-router-dom';
import { LoginPage } from '../features/auth/LoginPage';
import { DashboardPage } from '../features/dashboard/DashboardPage';
import { FolderBrowserPage } from '../features/browser/FolderBrowserPage';
import { SearchResultsPage } from '../features/search/SearchResultsPage';
import { AuditLogPage } from '../features/audit/AuditLogPage';
import { TrashPage } from '../features/trash/TrashPage';
import { UserManagementPage } from '../features/users/UserManagementPage';
import { PublicSharePage } from '../features/publicShare/PublicSharePage';
import { ProtectedRoute } from './ProtectedRoute';
import { RequireRole } from './RequireRole';

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  // §22.11 — no auth, no app shell: outside ProtectedRoute entirely.
  { path: '/s/:token', element: <PublicSharePage /> },
  {
    element: <ProtectedRoute />,
    children: [
      { path: '/', element: <Navigate to="/browse" replace /> },
      { path: '/browse/:folderId?', element: <FolderBrowserPage /> },
      { path: '/dashboard', element: <DashboardPage /> },
      { path: '/search', element: <SearchResultsPage /> },
      { path: '/trash', element: <TrashPage /> },
      {
        // §22.9/§22.10 — ADMIN only; anyone else is bounced to the dashboard.
        element: <RequireRole role="ADMIN" />,
        children: [
          { path: '/admin/audit-log', element: <AuditLogPage /> },
          { path: '/admin/users', element: <UserManagementPage /> },
        ],
      },
    ],
    // AppShell (rendered by ProtectedRoute) provides the persistent nav
    // sidebar/mobile drawer — each child page above renders content only.
  },
]);

