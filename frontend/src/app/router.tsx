import { createBrowserRouter, Navigate } from 'react-router-dom';
import { LoginPage } from '../features/auth/LoginPage';
import { DashboardPage } from '../features/dashboard/DashboardPage';
import { FolderBrowserPage } from '../features/browser/FolderBrowserPage';
import { ProtectedRoute } from './ProtectedRoute';

export const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  {
    element: <ProtectedRoute />,
    children: [
      { path: '/', element: <Navigate to="/browse" replace /> },
      { path: '/browse/:folderId?', element: <FolderBrowserPage /> },
      { path: '/dashboard', element: <DashboardPage /> },
    ],
  },
]);
