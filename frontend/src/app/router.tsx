import { createBrowserRouter, Navigate } from 'react-router-dom';
import { LoginPage } from '../features/auth/LoginPage';
import { DashboardPage } from '../features/dashboard/DashboardPage';
import { FolderBrowserPage } from '../features/browser/FolderBrowserPage';
import { SearchResultsPage } from '../features/search/SearchResultsPage';
import { AuditLogPage } from '../features/audit/AuditLogPage';
import { PublicSharePage } from '../features/publicShare/PublicSharePage';
import { ProtectedRoute } from './ProtectedRoute';

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
      { path: '/admin/audit-log', element: <AuditLogPage /> },
    ],
  },
]);
