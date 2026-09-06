/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import type { LucideIcon } from 'lucide-react';
import { ChevronRight } from 'lucide-react';
import { Link } from 'react-router-dom';

interface StatCardProps {
  icon: LucideIcon;
  iconClassName: string;
  label: string;
  value: string | number;
  to?: string;
}

/** One tile in the Browse page's stat row — colored icon, label, value, optional link-through. */
export function StatCard({ icon: Icon, iconClassName, label, value, to }: StatCardProps) {
  const content = (
    <>
      <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${iconClassName}`}>
        <Icon size={18} />
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-xs text-slate-500 dark:text-slate-400">{label}</p>
        <p className="text-lg font-semibold text-slate-900 dark:text-slate-100">{value}</p>
      </div>
      {to && <ChevronRight size={16} className="shrink-0 text-slate-300 dark:text-slate-600" />}
    </>
  );

  const className =
    'flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-800';

  if (to) {
    return (
      <Link to={to} className={`${className} transition-colors hover:border-slate-300 dark:hover:border-slate-600`}>
        {content}
      </Link>
    );
  }

  return <div className={className}>{content}</div>;
}
