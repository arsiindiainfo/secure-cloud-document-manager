/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import {
  FolderOpen,
  Home,
  LogOut,
  Menu,
  ScrollText,
  Search,
  Trash2,
  Users,
  X,
  type LucideIcon,
} from 'lucide-react';
import { useState } from 'react';
import { NavLink, Outlet, useNavigate } from 'react-router-dom';
import { BrandFooter } from '../components/BrandFooter';
import { BrandLogo } from '../components/BrandLogo';
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

const AVATAR_COLORS = ['bg-indigo-600', 'bg-purple-600', 'bg-pink-600', 'bg-emerald-600', 'bg-amber-600', 'bg-sky-600'];

function initialsOf(name: string | undefined): string {
  if (!name) return '?';
  const parts = name.trim().split(/\s+/);
  const first = parts[0]?.[0] ?? '';
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : '';
  return (first + last).toUpperCase();
}

function avatarColorFor(seed: string | undefined): string {
  if (!seed) return AVATAR_COLORS[0];
  let hash = 0;
  for (let i = 0; i < seed.length; i++) hash = (hash * 31 + seed.charCodeAt(i)) >>> 0;
  return AVATAR_COLORS[hash % AVATAR_COLORS.length];
}

/**
 * §31.2 — one persistent shell (sidebar nav + mobile drawer + BrandFooter)
 * wrapping every authenticated screen, rather than each page duplicating
 * its own header. Replaces the per-page <header> that used to overflow on
 * narrow screens (a row of 5+ buttons with no wrap/collapse).
 */
export function AppShell() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [mobileNavOpen, setMobileNavOpen] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const navItems = user?.role === 'ADMIN' ? [...NAV_ITEMS, ...ADMIN_NAV_ITEMS] : NAV_ITEMS;

  function handleSearchSubmit(e: React.FormEvent) {
    e.preventDefault();
    const q = searchQuery.trim();
    if (q !== '') navigate(`/search?q=${encodeURIComponent(q)}`);
  }

  return (
    <div className="flex min-h-screen flex-col bg-slate-50 dark:bg-slate-900 md:flex-row">
      {/* Mobile top bar */}
      <div className="flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800 md:hidden">
        <BrandLogo className="h-6" />
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
          <BrandLogo className="h-6" />
          <button
            type="button"
            onClick={() => setMobileNavOpen(false)}
            aria-label="Close menu"
            className="text-slate-400 md:hidden"
          >
            <X size={18} />
          </button>
        </div>

        <form onSubmit={handleSearchSubmit} className="border-b border-slate-200 p-3 dark:border-slate-700">
          <div className="relative">
            <Search size={15} className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              placeholder="Search documents…"
              className="w-full rounded-md border border-slate-300 py-1.5 pl-8 pr-2.5 text-xs dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
            />
          </div>
        </form>

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

        <div className="flex items-center gap-3 border-t border-slate-200 p-3 dark:border-slate-700">
          <div
            className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white ${avatarColorFor(user?.email)}`}
          >
            {initialsOf(user?.name)}
          </div>
          <div className="min-w-0 flex-1">
            <div className="truncate text-xs font-medium text-slate-700 dark:text-slate-200">{user?.name}</div>
            <div className="truncate text-xs text-slate-400 dark:text-slate-500">{user?.email}</div>
            <button
              onClick={() => void logout()}
              className="mt-1 flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
            >
              <LogOut size={12} />
              Sign out
            </button>
          </div>
        </div>
      </aside>

      <div className="flex flex-1 flex-col overflow-x-hidden">
        <main className="flex-1 p-4 md:p-6">
          <Outlet />
        </main>
        <BrandFooter />
      </div>
    </div>
  );
}
