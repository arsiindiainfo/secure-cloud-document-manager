/** §31.2 — copyright banner shown at the bottom of standalone pages (login, public share landing). */
export function BrandFooter() {
  return (
    <p className="mt-8 text-center text-xs text-slate-400 dark:text-slate-500">
      © {new Date().getFullYear()} Arsi India Info. All rights reserved.
    </p>
  );
}
