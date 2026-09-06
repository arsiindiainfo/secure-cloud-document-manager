/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

interface PageHeaderProps {
  icon: LucideIcon;
  iconClassName: string;
  title: string;
  subtitle?: ReactNode;
  actions?: ReactNode;
}

/** Colored-icon title block used at the top of every admin/list page, matching the Browse page's design language. */
export function PageHeader({ icon: Icon, iconClassName, title, subtitle, actions }: PageHeaderProps) {
  return (
    <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div className="flex items-center gap-3">
        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${iconClassName}`}>
          <Icon size={19} />
        </div>
        <div>
          <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">{title}</h1>
          {subtitle && <p className="text-sm text-slate-500 dark:text-slate-400">{subtitle}</p>}
        </div>
      </div>
      {actions}
    </div>
  );
}
