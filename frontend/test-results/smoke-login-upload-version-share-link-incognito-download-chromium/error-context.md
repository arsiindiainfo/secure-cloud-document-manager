# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke.spec.ts >> login, upload, version, share link, incognito download
- Location: e2e\smoke.spec.ts:12:1

# Error details

```
Test timeout of 600000ms exceeded.
```

```
Error: locator.fill: Test timeout of 600000ms exceeded.
Call log:
  - waiting for getByLabel('Name', { exact: true })

```

# Page snapshot

```yaml
- generic [ref=e3]:
  - banner [ref=e4]:
    - generic [ref=e5]:
      - img "Arsi India Info" [ref=e6]
      - navigation "Breadcrumb" [ref=e7]:
        - generic [ref=e8]: Home
    - generic [ref=e10]:
      - textbox "Search documents…" [ref=e12]
      - button "Trash" [ref=e13]
      - button "Users" [ref=e14]
      - button "Audit log" [ref=e15]
      - generic [ref=e16]: Ava Admin
      - button "Sign out" [ref=e17]
  - generic [ref=e18]:
    - generic [ref=e19]:
      - heading "Home" [level=1] [ref=e20]
      - button "New folder" [ref=e22]
    - list [ref=e24]:
      - listitem [ref=e25] [cursor=pointer]:
        - generic [ref=e26]:
          - generic [ref=e27]: 📁
          - text: lambda-e2e-ce8c75cc
        - generic [ref=e28]:
          - button "Rename" [ref=e29]
          - button "Delete" [ref=e30]
      - listitem [ref=e31] [cursor=pointer]:
        - generic [ref=e32]:
          - generic [ref=e33]: 📁
          - text: lambda-e2e-f115ec9a
        - generic [ref=e34]:
          - button "Rename" [ref=e35]
          - button "Delete" [ref=e36]
  - generic [ref=e38]:
    - heading "New folder" [level=2] [ref=e39]
    - generic [ref=e40]: Name *
    - textbox "Name" [active] [ref=e41]
    - generic [ref=e42]:
      - button "Cancel" [ref=e43]
      - button "Create" [disabled] [ref=e44]
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test';
  2  | 
  3  | // §25 — the full plan Definition of Done smoke suite: login → upload a
  4  | // file → create a version → generate a share link → open it in incognito
  5  | // → download. Runs against the real stack (see playwright.config.ts).
  6  | 
  7  | const PNG_V1 =
  8  |   'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
  9  | const PNG_V2 =
  10 |   'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADklEQVR42mNk+M+ACzEACjMCAWwLXDwAAAAASUVORK5CYIA=';
  11 | 
  12 | test('login, upload, version, share link, incognito download', async ({ page, browser }) => {
  13 |   await page.goto('/login');
  14 |   await page.getByLabel('Email').fill('admin@meridian.test');
  15 |   await page.getByLabel('Password').fill('Passw0rd!');
  16 |   await page.getByRole('button', { name: 'Sign in' }).click();
  17 |   await expect(page).toHaveURL(/\/browse/);
  18 | 
  19 |   const folderName = `e2e-${Date.now()}`;
  20 |   await page.getByRole('button', { name: 'New folder' }).click();
> 21 |   await page.getByLabel('Name', { exact: true }).fill(folderName);
     |                                                  ^ Error: locator.fill: Test timeout of 600000ms exceeded.
  22 |   await page.getByRole('button', { name: 'Create' }).click();
  23 |   await expect(page.getByText(folderName)).toBeVisible();
  24 |   await page.getByText(folderName).click();
  25 | 
  26 |   // Empty folder: both the header "Upload" control and the empty-state
  27 |   // "Upload files" control render a hidden <input type="file">; either
  28 |   // works, so just take the first match.
  29 |   await page.locator('input[type="file"]').first().setInputFiles({
  30 |     name: 'smoke.png',
  31 |     mimeType: 'image/png',
  32 |     buffer: Buffer.from(PNG_V1, 'base64'),
  33 |   });
  34 |   await expect(page.getByText('smoke.png')).toBeVisible({ timeout: 15_000 });
  35 | 
  36 |   await page.getByText('smoke.png').click();
  37 |   const panel = page.getByTestId('document-detail-panel');
  38 |   await expect(panel.getByText('v1', { exact: false })).toBeVisible();
  39 | 
  40 |   await panel.locator('label:has-text("Upload new version") input[type="file"]').setInputFiles({
  41 |     name: 'smoke-v2.png',
  42 |     mimeType: 'image/png',
  43 |     buffer: Buffer.from(PNG_V2, 'base64'),
  44 |   });
  45 |   await expect(panel.getByText('v2', { exact: false })).toBeVisible({ timeout: 15_000 });
  46 | 
  47 |   await panel.getByRole('button', { name: 'Share' }).click();
  48 |   await page.getByRole('button', { name: 'Generate link' }).click();
  49 |   const shareUrl = await page.locator('input[readonly]').inputValue({ timeout: 15_000 });
  50 |   expect(shareUrl).toContain('/s/');
  51 | 
  52 |   const incognitoContext = await browser.newContext();
  53 |   const incognitoPage = await incognitoContext.newPage();
  54 |   await incognitoPage.goto(shareUrl);
  55 |   await expect(incognitoPage.getByText('smoke.png')).toBeVisible({ timeout: 15_000 });
  56 | 
  57 |   const downloadPromise = incognitoPage.waitForEvent('download');
  58 |   await incognitoPage.getByRole('link', { name: /Download|View/ }).click();
  59 |   const download = await downloadPromise;
  60 |   expect(download.suggestedFilename()).toBeTruthy();
  61 | 
  62 |   await incognitoContext.close();
  63 | });
  64 | 
```