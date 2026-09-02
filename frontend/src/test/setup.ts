import { afterEach } from 'vitest';
import { cleanup } from '@testing-library/react';
import '@testing-library/jest-dom/vitest';

// Not relying on vitest's `globals: true` (kept off so every test file is
// explicit about what it imports), so RTL's own auto-cleanup-on-`afterEach`
// detection (which looks for a global `afterEach`) never fires — do it here.
afterEach(() => {
  cleanup();
});
