#!/usr/bin/env node
/**
 * Secure Cloud Document Manager
 * Copyright (c) 2026 Arsi India Info. All rights reserved.
 * Licensed under the MIT License -- see LICENSE. The "Arsi India Info"
 * name and logo are separately protected -- see TRADEMARK.md.
 */

// §31.2 — CI license-header-check: every authored PHP/TS/JS source file must
// carry the copyright banner. Run from the repo root: `node scripts/check-license-headers.mjs`.

import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';

const ROOT = new URL('..', import.meta.url).pathname.replace(/^\/([A-Za-z]):/, '$1:');
const MARKER = 'Copyright (c) 2026 Arsi India Info';

const TARGETS = [
  { dir: join(ROOT, 'backend', 'app'), exts: ['.php'] },
  { dir: join(ROOT, 'backend', 'tests'), exts: ['.php'] },
  { dir: join(ROOT, 'frontend', 'src'), exts: ['.ts', '.tsx'] },
  { dir: join(ROOT, 'frontend', 'e2e'), exts: ['.ts'] },
  { dir: join(ROOT, 'infrastructure', 'aws', 'lambda', 'processing-worker'), exts: ['.js'], recursive: false },
];

function walk(dir, exts, recursive = true) {
  let results = [];
  let entries;
  try {
    entries = readdirSync(dir);
  } catch {
    return results;
  }
  for (const entry of entries) {
    const full = join(dir, entry);
    const stats = statSync(full);
    if (stats.isDirectory()) {
      if (entry === 'node_modules' || entry === 'vendor') continue;
      if (recursive) results = results.concat(walk(full, exts, recursive));
    } else if (exts.includes(extname(entry))) {
      results.push(full);
    }
  }
  return results;
}

const missing = [];
for (const target of TARGETS) {
  const files = walk(target.dir, target.exts, target.recursive !== false);
  for (const file of files) {
    const content = readFileSync(file, 'utf8');
    if (!content.includes(MARKER)) {
      missing.push(file);
    }
  }
}

if (missing.length > 0) {
  console.error(`Missing copyright header in ${missing.length} file(s):`);
  for (const file of missing) console.error(`  - ${file}`);
  process.exit(1);
}

console.log('All source files carry the copyright header.');
