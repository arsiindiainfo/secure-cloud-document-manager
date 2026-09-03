/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

// Minimal ambient typing for the Google reCAPTCHA v2 script — just the
// explicit-render surface this app actually uses (see components/Recaptcha.tsx).
interface Grecaptcha {
  render(
    container: string | HTMLElement,
    options: {
      sitekey: string;
      callback?: (token: string) => void;
      'expired-callback'?: () => void;
      'error-callback'?: () => void;
    },
  ): number;
  reset(widgetId?: number): void;
  ready(callback: () => void): void;
}

interface Window {
  grecaptcha?: Grecaptcha;
}
