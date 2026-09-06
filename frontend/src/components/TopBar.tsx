/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { Bell, ChevronDown, KeyRound, LogOut, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { avatarColorFor, initialsOf } from '../lib/avatar';
import { useAuth } from '../features/auth/useAuth';
import { ChangePasswordDialog } from '../features/users/ChangePasswordDialog';

/** Persistent search + notification + account menu, spanning the main content area (§31.2). */
export function TopBar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [searchQuery, setSearchQuery] = useState('');
  const [menuOpen, setMenuOpen] = useState(false);
  const [showChangePassword, setShowChangePassword] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    function handleClickOutside(e: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(e.target as Node)) setMenuOpen(false);
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  function handleSearchSubmit(e: React.FormEvent) {
    e.preventDefault();
    const q = searchQuery.trim();
    if (q !== '') navigate(`/search?q=${encodeURIComponent(q)}`);
  }

  return (
    <div className="flex items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-800 md:px-6">
      <form onSubmit={handleSearchSubmit} className="min-w-0 flex-1">
        <div className="relative max-w-lg">
          <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search documents, folders…"
            className="w-full rounded-full border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-sm focus:border-blue-400 focus:outline-none dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
          />
        </div>
      </form>

      <button
        type="button"
        aria-label="Notifications"
        className="shrink-0 rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-700 dark:hover:text-slate-200"
      >
        <Bell size={18} />
      </button>

      <div ref={menuRef} className="relative shrink-0">
        <button
          type="button"
          onClick={() => setMenuOpen((o) => !o)}
          className="flex items-center gap-2 rounded-full py-1 pl-1 pr-2 hover:bg-slate-100 dark:hover:bg-slate-700"
        >
          <div className={`flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold text-white ${avatarColorFor(user?.email)}`}>
            {initialsOf(user?.name)}
          </div>
          <span className="hidden text-sm font-medium text-slate-700 dark:text-slate-200 sm:inline">{user?.name}</span>
          <ChevronDown size={14} className="hidden text-slate-400 sm:inline" />
        </button>

        {menuOpen && (
          <div className="absolute right-0 z-20 mt-2 w-52 rounded-md border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-800">
            <div className="border-b border-slate-100 px-3 py-2 dark:border-slate-700">
              <p className="truncate text-sm font-medium text-slate-700 dark:text-slate-200">{user?.name}</p>
              <p className="truncate text-xs text-slate-400 dark:text-slate-500">{user?.email}</p>
            </div>
            <button
              onClick={() => {
                setMenuOpen(false);
                setShowChangePassword(true);
              }}
              className="flex w-full items-center gap-2 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-700/50"
            >
              <KeyRound size={14} /> Change password
            </button>
            <button
              onClick={() => void logout()}
              className="flex w-full items-center gap-2 px-3 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-950/50"
            >
              <LogOut size={14} /> Sign out
            </button>
          </div>
        )}
      </div>

      {showChangePassword && (
        <ChangePasswordDialog onClose={() => setShowChangePassword(false)} onChanged={() => void logout()} />
      )}
    </div>
  );
}
