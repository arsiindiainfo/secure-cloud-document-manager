/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { FolderOpen, Home, Menu, ScrollText, Trash2, Users, X, type LucideIcon } from 'lucide-react';
import { useState } from 'react';
import { Link, NavLink, Outlet } from 'react-router-dom';
import { BrandFooter } from '../components/BrandFooter';
import { BrandLogo } from '../components/BrandLogo';
import { TopBar } from '../components/TopBar';
import { useAuth } from '../features/auth/useAuth';

const NAV_ITEMS: { to: string; label: string; icon: LucideIcon }[] = [
  { to: '/browse', label: 'Browse', icon: FolderOpen },
  { to: '/dashboard', label: 'Dashboard', icon: Home },
  { to: '/trash', label: 'Trash', icon: Trash2 },
];

const ADMIN_NAV_ITEMS: { to: string; label: string; icon: LucideIcon }[] = [
  { to: '/admin/users', label: 'Users', icon: Users },
  { to: '/admin/audit-log', label: 'Audit log', icon: ScrollText },
];

/**
 * §31.2 — sidebar nav + mobile drawer + a persistent TopBar (search,
 * notifications, account menu) wrapping every authenticated screen,
 * rather than each page duplicating its own header.
 */
export function AppShell() {
  const { user } = useAuth();
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const navItems = user?.role === 'ADMIN' ? [...NAV_ITEMS, ...ADMIN_NAV_ITEMS] : NAV_ITEMS;

  return (
    <div className="flex min-h-screen flex-col bg-slate-50 dark:bg-slate-900 md:flex-row">
      {/* Mobile top bar (logo + hamburger) */}
      <div className="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800 md:hidden">
        <Link to="/browse" onClick={() => setMobileNavOpen(false)}>
          <BrandLogo className="h-10" />
        </Link>
        <button
          type="button"
          onClick={() => setMobileNavOpen(true)}
          aria-label="Open menu"
          className="rounded-md border border-slate-200 p-2 text-slate-600 dark:border-slate-600 dark:text-slate-300"
        >
          <Menu size={20} />
        </button>
      </div>

      {/* Mobile drawer backdrop */}
      {mobileNavOpen && (
        <div
          className="fixed inset-0 z-40 bg-black/30 md:hidden"
          onClick={() => setMobileNavOpen(false)}
          aria-hidden="true"
        />
      )}

      <aside
        className={`fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col border-r border-slate-200 bg-white transition-transform duration-200 dark:border-slate-700 dark:bg-slate-800 md:static md:z-auto md:w-56 md:translate-x-0 ${
          mobileNavOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <div className="flex items-center justify-between border-b border-slate-200 px-4 py-4 dark:border-slate-700">
          <Link to="/browse" onClick={() => setMobileNavOpen(false)}>
            <BrandLogo className="h-10" />
          </Link>
          <button
            type="button"
            onClick={() => setMobileNavOpen(false)}
            aria-label="Close menu"
            className="text-slate-400 md:hidden"
          >
            <X size={18} />
          </button>
        </div>

        <nav className="flex-1 space-y-0.5 overflow-y-auto p-3">
          {navItems.map((item) => {
            const Icon = item.icon;
            return (
              <NavLink
                key={item.to}
                to={item.to}
                onClick={() => setMobileNavOpen(false)}
                className={({ isActive }) =>
                  `flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                    isActive
                      ? 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300'
                      : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700/50'
                  }`
                }
              >
                <Icon size={17} className="shrink-0" />
                {item.label}
              </NavLink>
            );
          })}
        </nav>
      </aside>

      <div className="flex flex-1 flex-col overflow-x-hidden">
        <TopBar />
        <main className="flex-1 p-4 md:p-6">
          <Outlet />
        </main>
        <BrandFooter />
      </div>
    </div>
  );
}
