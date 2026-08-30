import { Link } from 'react-router-dom';
import type { BreadcrumbEntry } from '../types/api';

/** `entries` is the API's ancestor chain (root-first, current folder last) — "Home" is prepended here. */
export function Breadcrumbs({ entries }: { entries: BreadcrumbEntry[] }) {
  const items: { id: number | null; name: string }[] = [{ id: null, name: 'Home' }, ...entries];

  return (
    <nav aria-label="Breadcrumb" className="flex items-center gap-1 text-sm text-slate-600 dark:text-slate-300">
      {items.map((item, index) => {
        const isLast = index === items.length - 1;
        const to = item.id === null ? '/browse' : `/browse/${item.id}`;
        return (
          <span key={item.id ?? 'root'} className="flex items-center gap-1">
            {index > 0 && <span className="text-slate-400">/</span>}
            {isLast ? (
              <span className="font-medium text-slate-900 dark:text-slate-100">{item.name}</span>
            ) : (
              <Link to={to} className="hover:text-blue-600 hover:underline dark:hover:text-blue-400">
                {item.name}
              </Link>
            )}
          </span>
        );
      })}
    </nav>
  );
}
