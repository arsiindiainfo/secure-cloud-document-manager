/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { useEffect, useRef } from 'react';

const SITE_KEY = import.meta.env.VITE_RECAPTCHA_SITE_KEY as string | undefined;
const SCRIPT_ID = 'grecaptcha-script';

interface RecaptchaProps {
  onChange: (token: string | null) => void;
}

/**
 * §15 — Google reCAPTCHA v2 checkbox, rendered explicitly (not the
 * auto-render `.g-recaptcha` div) so token changes flow into React state
 * via a real callback instead of a global window function. Renders nothing
 * if no site key is configured, so local dev/tests never need one.
 */
export function Recaptcha({ onChange }: RecaptchaProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const widgetId = useRef<number | null>(null);

  useEffect(() => {
    if (!SITE_KEY || !containerRef.current) return;

    function renderWidget() {
      if (!containerRef.current || !window.grecaptcha || widgetId.current !== null) return;
      widgetId.current = window.grecaptcha.render(containerRef.current, {
        sitekey: SITE_KEY!,
        callback: (token) => onChange(token),
        'expired-callback': () => onChange(null),
        'error-callback': () => onChange(null),
      });
    }

    if (window.grecaptcha) {
      window.grecaptcha.ready(renderWidget);
      return;
    }

    let script = document.getElementById(SCRIPT_ID) as HTMLScriptElement | null;
    if (!script) {
      script = document.createElement('script');
      script.id = SCRIPT_ID;
      script.src = 'https://www.google.com/recaptcha/api.js?render=explicit';
      script.async = true;
      script.defer = true;
      document.head.appendChild(script);
    }
    script.addEventListener('load', () => window.grecaptcha?.ready(renderWidget));
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  if (!SITE_KEY) {
    return (
      <p className="text-xs text-amber-600 dark:text-amber-400">
        Captcha not configured (VITE_RECAPTCHA_SITE_KEY unset) — skipping in this environment.
      </p>
    );
  }

  return <div ref={containerRef} />;
}
