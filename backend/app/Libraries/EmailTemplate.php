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

    /**
     * Registration verification email — unlike render(), this doesn't share
     * the minimal one-card shell (used for admin notices and cron reports):
     * it keeps the same header/hero visual polish, but stays purely
     * transactional (verify button + the one fact relevant to activating
     * an account). An earlier version bundled in 3 cross-promotional demo
     * cards and a "hire me" sales pitch — that shape (many outbound links
     * to different domains, marketing language, in a supposed
     * verify-your-email message) reads as spam to content filters
     * independent of sender authentication, and was landing in spam even
     * for brand-new recipients. Demo cross-promotion belongs in a
     * separate, later email — not the one gating account access.
     */
    public static function renderVerification(string $name, string $verifyUrl): string
    {
        $safeName = esc($name, 'html');
        $safeUrl  = esc($verifyUrl, 'attr');
        $year     = date('Y');
        $logoUrl  = 'https://www.arsiindiainfo.com/assets/images/logo-horizontal.png';

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
              <meta charset="UTF-8">
              <meta name="viewport" content="width=device-width, initial-scale=1.0">
              <title>Verify your Arsi India Info Demo Account</title>
            </head>
            <body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#14213d;">
              <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
                Verify your email address to activate your Secure Cloud Document Manager account.
              </div>

              <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f4f7fb;">
                <tr>
                  <td align="center" style="padding:30px 15px;">
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
                           style="max-width:680px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 8px 30px rgba(31,59,93,.08);">

                      <!-- Header -->
                      <tr>
                        <td style="padding:28px 36px;border-bottom:1px solid #edf1f7;background:#ffffff;">
                          <img src="{$logoUrl}" alt="Arsi India Info" width="360"
                               style="display:block;width:360px;max-width:100%;height:auto;border:0;outline:none;text-decoration:none;">
                        </td>
                      </tr>

                      <!-- Hero -->
                      <tr>
                        <td style="padding:42px 36px 30px;background:linear-gradient(135deg,#eef6ff 0%,#ffffff 58%,#f3f8ff 100%);">
                          <div style="display:inline-block;padding:9px 14px;border-radius:30px;background:#e7f1ff;color:#1769d2;font-size:12px;font-weight:700;">
                            ACCOUNT VERIFICATION
                          </div>

                          <h1 style="margin:18px 0 12px;font-size:30px;line-height:1.2;color:#102a56;">
                            Welcome to Arsi India Info
                          </h1>

                          <p style="margin:0 0 18px;font-size:16px;line-height:1.7;color:#52627a;">
                            Hi {$safeName},
                          </p>

                          <p style="margin:0 0 24px;font-size:16px;line-height:1.7;color:#52627a;">
                            Thank you for registering for the Secure Cloud Document Manager demo.
                            Please verify your email address to activate your account and continue.
                          </p>

                          <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                              <td style="border-radius:10px;background:#1769e8;">
                                <a href="{$safeUrl}"
                                   style="display:inline-block;padding:15px 28px;color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;">
                                  Verify My Email
                                </a>
                              </td>
                            </tr>
                          </table>

                          <p style="margin:18px 0 0;font-size:12px;line-height:1.6;color:#8492a6;">
                            For security, this verification link is intended only for the email address used during registration.
                            The link expires in 24 hours.
                          </p>
                          <p style="margin:10px 0 0;font-size:12px;line-height:1.6;color:#8492a6;word-break:break-all;">
                            Or paste this link into your browser: {$safeUrl}
                          </p>
                        </td>
                      </tr>

                      <!-- Public demo notice -->
                      <tr>
                        <td style="padding:26px 36px 6px;">
                          <div style="padding:18px 20px;border:1px solid #e5ebf3;border-radius:12px;background:#fbfdff;">
                            <div style="font-size:13px;font-weight:800;color:#1769e8;margin-bottom:8px;">
                              ABOUT THIS PUBLIC DEMO
                            </div>
                            <p style="margin:0;font-size:13px;line-height:1.7;color:#66758b;">
                              This is a shared public demo, so a 30-day retention policy keeps storage in check:
                              only your 5 most recently uploaded files are kept once they're older than 30 days &mdash;
                              older files beyond that are permanently removed automatically. Re-upload anything you
                              still need, and avoid uploading anything sensitive to this demo environment.
                            </p>
                          </div>
                        </td>
                      </tr>

                      <!-- Footer -->
                      <tr>
                        <td style="padding:24px 36px;text-align:center;background:#ffffff;">
                          <p style="margin:0 0 8px;font-size:13px;color:#52627a;">
                            Created by <strong>Rajib Majumder</strong> &middot; Arsi India Info
                          </p>
                          <p style="margin:0;font-size:11px;line-height:1.6;color:#9aa7b8;">
                            This is an automated verification email. If you did not create this account,
                            you can safely ignore this message.
                          </p>
                          <p style="margin:14px 0 0;font-size:11px;color:#a2adbb;">
                            &copy; {$year} Arsi India Info. All rights reserved.
                          </p>
                        </td>
                      </tr>

                    </table>
                  </td>
                </tr>
              </table>
            </body>
            </html>
            HTML;
    }

}
