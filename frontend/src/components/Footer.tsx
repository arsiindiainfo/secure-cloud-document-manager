/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { BadgeCheck, Code2, Mail, ShieldCheck, Zap } from 'lucide-react';
import { BrandLogo } from './BrandLogo';

const WHATSAPP_NUMBER_DISPLAY = '+91 94337 96101';
const WHATSAPP_LINK = 'https://wa.me/919433796101';
const TEAMS_EMAIL = 'arsi.india.info@gmail.com';
const TEAMS_LINK = `https://teams.microsoft.com/l/chat/0/0?users=${TEAMS_EMAIL}`;
const UPWORK_LINK = 'https://www.upwork.com/freelancers/~0172bf8ee328825a5a?mp_source=share';

function WhatsAppIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-[18px] w-[18px]" fill="currentColor" aria-hidden="true">
      <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38a9.87 9.87 0 0 0 4.74 1.21h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm0 1.67c2.19 0 4.25.85 5.79 2.4a8.2 8.2 0 0 1 2.41 5.83c0 4.55-3.7 8.25-8.25 8.25a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.18 8.18 0 0 1-1.26-4.37c0-4.55 3.7-8.25 8.29-8.25Zm-4.55 4.6c-.16 0-.42.06-.64.31-.22.25-.85.83-.85 2.03s.87 2.36.99 2.52c.12.16 1.7 2.68 4.19 3.65 2.07.81 2.49.65 2.94.61.45-.04 1.45-.59 1.66-1.16.2-.57.2-1.06.14-1.16-.06-.1-.22-.16-.45-.28-.24-.12-1.45-.72-1.68-.8-.22-.08-.39-.12-.55.12-.16.24-.63.8-.78.97-.14.16-.28.18-.52.06-.24-.12-1.02-.38-1.94-1.2-.72-.64-1.2-1.43-1.35-1.67-.14-.24-.02-.37.11-.5.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.55-1.34-.76-1.83-.2-.48-.4-.42-.55-.42Z" />
    </svg>
  );
}

function TeamsIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-[18px] w-[18px]" fill="currentColor" aria-hidden="true">
      <path d="M16.5 8.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm-6.75-.75a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM17.7 9.5h-3.1a1.3 1.3 0 0 0-1.3 1.3v3.6c0 1.88.94 3.54 2.38 4.53.28.19.65.19.93 0 1.65-1.13 2.69-3 2.69-5.08v-3.05c0-.72-.58-1.3-1.3-1.3ZM9.75 9.5H3.4c-.5 0-.9.4-.9.9v4.1c0 3.03 2.02 5.6 4.8 6.4.24.07.5.07.74 0 2.29-.66 4.03-2.57 4.55-4.92.1-.44-.24-.86-.7-.86h-.6a.5.5 0 0 1-.5-.5v-3.62c0-.83-.68-1.5-1.5-1.5h.46Z" />
    </svg>
  );
}

function UpworkIcon() {
  return (
    <svg viewBox="0 0 24 24" className="h-[18px] w-[18px]" fill="currentColor" aria-hidden="true">
      <path d="M18.56 7.68c-1.85 0-3.28 1.23-3.9 3.14-.32-.5-.6-1.07-.83-1.63l-.42-1.13h-2.24v5.35c0 1.07-.87 1.93-1.93 1.93s-1.93-.86-1.93-1.93V8.06H5.06v5.35c0 2.3 1.87 4.19 4.18 4.19 2.2 0 4-1.7 4.16-3.86l.4 1.13c.4 1.03.98 1.94 1.72 2.7l-1.05 4.9h2.28l.79-3.68c.63.24 1.32.37 2.02.37 2.94 0 5.32-2.4 5.32-5.34s-2.38-5.14-5.32-5.14Zm0 8.24c-.86 0-1.66-.32-2.28-.9l.2-.94c.24-1.36.98-2.25 1.98-2.25 1.11 0 2.01.98 2.01 2.05 0 1.13-.9 2.04-1.91 2.04Z" />
    </svg>
  );
}

const CONTACT_CHIPS = [
  {
    key: 'email',
    href: 'mailto:arsi.india.info@gmail.com',
    icon: <Mail size={18} />,
    iconBg: 'bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-300',
    label: 'Email',
    value: 'arsi.india.info@gmail.com',
  },
  {
    key: 'whatsapp',
    href: WHATSAPP_LINK,
    external: true,
    icon: <WhatsAppIcon />,
    iconBg: 'bg-green-100 text-green-600 dark:bg-green-950 dark:text-green-300',
    label: 'WhatsApp',
    value: WHATSAPP_NUMBER_DISPLAY,
  },
  {
    key: 'teams',
    href: TEAMS_LINK,
    external: true,
    icon: <TeamsIcon />,
    iconBg: 'bg-indigo-100 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-300',
    label: 'Microsoft Teams',
    value: 'Chat on Teams',
  },
  {
    key: 'upwork',
    href: UPWORK_LINK,
    external: true,
    icon: <UpworkIcon />,
    iconBg: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-300',
    label: 'Hire on Upwork',
    value: 'View my profile',
  },
];

/** Persistent branding + contact surface at the bottom of the authenticated app (§31.2). */
export function Footer() {
  return (
    <footer className="border-t border-slate-200 bg-gradient-to-br from-white via-blue-50/40 to-white dark:border-slate-700 dark:from-slate-900 dark:via-slate-800 dark:to-slate-900">
      <div className="mx-auto flex max-w-[1600px] flex-col divide-y divide-slate-200 px-6 py-2 dark:divide-slate-700 lg:flex-row lg:items-center lg:divide-x lg:divide-y-0">
        <div className="flex shrink-0 items-center py-4 lg:pr-6">
          <a href="https://www.arsiindiainfo.com/" target="_blank" rel="noopener noreferrer">
            <BrandLogo className="h-8" />
          </a>
        </div>

        <div className="max-w-sm py-4 lg:px-6">
          <h3 className="text-base font-bold text-slate-900 dark:text-white">
            Need a custom solution for your business?
          </h3>
          <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">
            This demo shows what we can build for you. Let&apos;s build it together &mdash; custom development and
            ongoing support available.
          </p>
          <p className="mt-2 flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
            Built by :
            <span className="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300">
              Rajib Majumder
            </span>
          </p>
        </div>

        <div className="flex flex-wrap gap-2.5 py-4 lg:flex-nowrap lg:pl-6">
          {CONTACT_CHIPS.map((chip) => (
            <a
              key={chip.key}
              href={chip.href}
              target={chip.external ? '_blank' : undefined}
              rel={chip.external ? 'noopener noreferrer' : undefined}
              className="flex items-center gap-2.5 whitespace-nowrap rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-700 dark:bg-slate-800"
            >
              <span className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${chip.iconBg}`}>
                {chip.icon}
              </span>
              <span className="min-w-0">
                <span className="block text-xs font-semibold text-slate-900 dark:text-white">{chip.label}</span>
                <span className="block truncate text-[11px] text-slate-500 dark:text-slate-400">{chip.value}</span>
              </span>
            </a>
          ))}
        </div>
      </div>

      <div className="bg-slate-950 px-6 py-3">
        <div className="mx-auto flex max-w-[1600px] flex-col items-center justify-between gap-2 text-[11px] text-slate-400 sm:flex-row">
          <span>&copy; {new Date().getFullYear()} Arsi India Info. All rights reserved.</span>
          <span className="flex items-center gap-4">
            <span className="flex items-center gap-1.5">
              <ShieldCheck size={13} /> Secure
            </span>
            <span className="flex items-center gap-1.5">
              <Zap size={13} /> Fast
            </span>
            <span className="flex items-center gap-1.5">
              <BadgeCheck size={13} /> Reliable
            </span>
            <span className="hidden items-center gap-1.5 sm:flex">
              <Code2 size={13} /> Powered by React &amp; PHP CodeIgniter
            </span>
          </span>
        </div>
      </div>
    </footer>
  );
}
