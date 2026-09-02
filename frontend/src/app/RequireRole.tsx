import { Navigate, Outlet } from 'react-router-dom';
import { useAuth } from '../features/auth/useAuth';
import type { Role } from '../types/api';

/**
 * Nest as a wrapping route element (inside `ProtectedRoute`, which already
 * guarantees `user` is loaded and non-null) to gate a subtree of routes to a
 * single role — e.g. §22.9's Audit Log and §22.10's User Management, both
 * ADMIN only. Anyone else lands back on the dashboard rather than seeing an
 * empty/broken page for a route they had no business hitting via the URL bar.
 */
export function RequireRole({ role }: { role: Role }) {
  const { user } = useAuth();

  if (user?.role !== role) {
    return <Navigate to="/dashboard" replace />;
  }

  return <Outlet />;
}
