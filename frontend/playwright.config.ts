import { defineConfig, devices } from '@playwright/test';

// §25 — the smoke suite exercises the real stack: Vite dev server + the
// live backend (php spark serve) + LocalStack S3. It does not start either
// server itself — both are expected to already be running (see
// docs/testing.md) since they're shared with the rest of this session's
// manual verification.
export default defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  retries: 0,
  timeout: 600_000,
  expect: { timeout: 30_000 },
  use: {
    baseURL: 'http://localhost:5173',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
