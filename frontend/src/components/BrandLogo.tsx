/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

interface BrandLogoProps {
  className?: string;
}

/** §31.2 — Arsi India Info branding, shared across every screen that shows it. */
export function BrandLogo({ className = 'h-8' }: BrandLogoProps) {
  return <img src="/logo.png" alt="Arsi India Info" className={className} />;
}

