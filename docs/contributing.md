# Contributing

## Branch naming

`<type>/<short-description>`, matching the commit type prefixes below:

```
feat/share-link-download-cap
fix/folder-restore-parent-deleted
docs/backend-setup-walkthrough
chore/bump-swagger-php
```

## Commit messages — Conventional Commits

Every commit follows [Conventional Commits](https://www.conventionalcommits.org/): `type(scope): summary`, imperative
mood, no trailing period. `scope` is usually the feature area (a route group, a frontend feature folder, or a
cross-cutting concern like `ci`).

Examples in this project's own style:

```
feat(sharing): add share-link expiry validation
fix(documents): return 404 not 403 for non-participant access
docs(readme): add signed-URL security section
```

Common types: `feat`, `fix`, `docs`, `test`, `refactor`, `chore`. A breaking change gets a `!` after the type/scope
(`feat(api)!: ...`) with a `BREAKING CHANGE:` footer explaining the migration.

## Pull request outline

A PR description should cover:

- **What & why** — one or two sentences; link an issue if one exists.
- **How it was tested** — which of `composer test` / `npm run test` / `npm run test:e2e` you ran, and any manual
  verification (which screen, which seeded account).
- **Screenshots**, for any UI-visible change.
- **Checklist**:
  - [ ] Tests added/updated for the behavior changed
  - [ ] `composer test` / `npm run test` pass locally
  - [ ] No new PHPStan or ESLint warnings introduced
  - [ ] Copyright header present on any new file (see below)

## Required CI checks

`.github/workflows/ci.yml` is the source of truth — three jobs run on every push and pull request:

1. **`license-headers`** — `node scripts/check-license-headers.mjs` verifies every source file carries the standard
   Arsi India Info copyright banner (§31.2). Copy the banner from any neighboring file in the same directory rather
   than retyping it.
2. **`backend`** — against real MySQL + LocalStack service containers: `composer install`, migrate the test schema,
   `vendor/bin/phpstan analyse`, `vendor/bin/phpunit`.
3. **`frontend`** — `npm ci`, `npx tsc -b --noEmit`, `npx eslint .`, `npx vitest run`, `npm run build`.

All three must pass before a PR merges. Playwright is not part of CI (see [`docs/testing.md`](testing.md) for why) —
run it manually against a local stack if your change touches the upload/sharing flow it exercises.
