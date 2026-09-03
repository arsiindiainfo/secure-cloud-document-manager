/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

import { test, expect } from '@playwright/test';

// §25 — the full plan Definition of Done smoke suite: login → upload a
// file → create a version → generate a share link → open it in incognito
// → download. Runs against the real stack (see playwright.config.ts).

const PNG_V1 =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
const PNG_V2 =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADklEQVR42mNk+M+ACzEACjMCAWwLXDwAAAAASUVORK5CYIA=';

test('login, upload, version, share link, incognito download', async ({ page, browser }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('admin@meridian.test');
  await page.getByLabel('Password').fill('Passw0rd!');
  await page.getByRole('button', { name: 'Sign in' }).click();
  await expect(page).toHaveURL(/\/browse/);

  const folderName = `e2e-${Date.now()}`;
  await page.getByRole('button', { name: 'New folder' }).click();
  await page.getByLabel('Name', { exact: true }).fill(folderName);
  await page.getByRole('button', { name: 'Create' }).click();
  await expect(page.getByText(folderName)).toBeVisible();
  await page.getByText(folderName).click();

  // Empty folder: both the header "Upload" control and the empty-state
  // "Upload files" control render a hidden <input type="file">; either
  // works, so just take the first match.
  await page.locator('input[type="file"]').first().setInputFiles({
    name: 'smoke.png',
    mimeType: 'image/png',
    buffer: Buffer.from(PNG_V1, 'base64'),
  });
  await expect(page.getByText('smoke.png')).toBeVisible({ timeout: 15_000 });

  await page.getByText('smoke.png').click();
  const panel = page.getByTestId('document-detail-panel');
  await expect(panel.getByText('v1', { exact: false })).toBeVisible();

  await panel.locator('label:has-text("Upload new version") input[type="file"]').setInputFiles({
    name: 'smoke-v2.png',
    mimeType: 'image/png',
    buffer: Buffer.from(PNG_V2, 'base64'),
  });
  await expect(panel.getByText('v2', { exact: false })).toBeVisible({ timeout: 15_000 });

  await panel.getByRole('button', { name: 'Share' }).click();
  await page.getByRole('button', { name: 'Generate link' }).click();
  const shareUrl = await page.locator('input[readonly]').inputValue({ timeout: 15_000 });
  expect(shareUrl).toContain('/s/');

  const incognitoContext = await browser.newContext();
  const incognitoPage = await incognitoContext.newPage();
  await incognitoPage.goto(shareUrl);
  await expect(incognitoPage.getByText('smoke.png')).toBeVisible({ timeout: 15_000 });

  const downloadPromise = incognitoPage.waitForEvent('download');
  await incognitoPage.getByRole('link', { name: /Download|View/ }).click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toBeTruthy();

  await incognitoContext.close();
});

