<?php

/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

namespace App\Libraries;

/**
 * One branded HTML shell every outbound email renders through, so a
 * recipient's inbox always shows the same look (logo, card, footer)
 * instead of each call site hand-rolling its own markup. Email clients
 * need an absolute image URL — logo.png is served as a static frontend
 * asset, so this points at frontendUrl, not the API's own baseURL.
 */
class EmailTemplate
{
    /**
     * @param string      $title      Shown as the H1 inside the card.
     * @param string      $bodyHtml   Pre-escaped HTML — callers interpolate
     *                                already-escaped values themselves.
     * @param array{label: string, url: string}|null $cta Optional button.
     */
    public static function render(string $title, string $bodyHtml, ?array $cta = null): string
    {
        $logoUrl = rtrim(config('App')->frontendUrl, '/') . '/logo.png';
        $year    = date('Y');

        $ctaHtml = '';
        if ($cta !== null) {
            $ctaHtml = <<<HTML
                <table role="presentation" cellpadding="0" cellspacing="0" style="margin: 28px 0;">
                  <tr>
                    <td style="border-radius: 6px; background-color: #2563eb;">
                      <a href="{$cta['url']}" style="display: inline-block; padding: 12px 24px; font-size: 14px;
                        font-weight: 600; color: #ffffff; text-decoration: none;">{$cta['label']}</a>
                    </td>
                  </tr>
                </table>
                HTML;
        }

        return <<<HTML
            <!doctype html>
            <html>
              <body style="margin: 0; padding: 32px 16px; background-color: #f8fafc; font-family: -apple-system,
                BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width: 480px;
                  margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                  <tr>
                    <td style="padding: 32px;">
                      <img src="{$logoUrl}" alt="Arsi India Info" height="32" style="height: 32px; margin-bottom: 24px;" />
                      <h1 style="margin: 0 0 16px; font-size: 18px; font-weight: 600; color: #0f172a;">{$title}</h1>
                      <div style="font-size: 14px; line-height: 1.6; color: #334155;">{$bodyHtml}</div>
                      {$ctaHtml}
                    </td>
                  </tr>
                </table>
                <p style="max-width: 480px; margin: 16px auto 0; text-align: center; font-size: 12px; color: #94a3b8;">
                  &copy; {$year} Arsi India Info. All rights reserved.
                </p>
              </body>
            </html>
            HTML;
    }
}
