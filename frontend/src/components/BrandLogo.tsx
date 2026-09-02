interface BrandLogoProps {
  className?: string;
}

/** §31.2 — Arsi India Info branding, shared across every screen that shows it. */
export function BrandLogo({ className = 'h-8' }: BrandLogoProps) {
  return <img src="/logo.png" alt="Arsi India Info" className={className} />;
}
