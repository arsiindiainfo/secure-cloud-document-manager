import { useAuth } from '../auth/useAuth';

// Placeholder landing page for Phase 0 — the real Dashboard (§22.2: recent
// documents, storage summary, shared-folder quick links) is built in Phase 5.
export function DashboardPage() {
  const { user, logout } = useAuth();

  return (
    <div className="min-h-screen bg-slate-50 p-8 dark:bg-slate-900">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Welcome, {user?.name}
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Signed in as {user?.role.toLowerCase()} · {user?.email}
          </p>
        </div>
        <button
          onClick={() => void logout()}
          className="rounded-md border border-slate-300 px-3 py-2 text-sm text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-800"
        >
          Sign out
        </button>
      </div>
    </div>
  );
}
