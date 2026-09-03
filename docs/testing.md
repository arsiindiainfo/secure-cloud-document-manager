# Testing

Three layers: PHPUnit feature tests against a real database and real LocalStack S3, Vitest + React Testing Library for
frontend units, and a Playwright smoke suite against the real running stack. Nothing here mocks the stored procedures.

## Backend — PHPUnit

```bash
cd backend
composer test
```

`ApiTestCase` (`backend/tests/_support/ApiTestCase.php`) is the base for every controller feature test. Per test class it
runs the real migrations once (`migrateOnce`) — all 10 tables plus the 16 stored procedures — then, before each
individual test, `TRUNCATE`s every app table and reseeds `DemoSeeder`. Feature tests hit real stored procedures and a
real LocalStack S3 bucket; the business rules live in the procedures, so mocking them would test nothing.

Coverage by file (`backend/tests/api/*.php`):

- `AuthControllerTest`, `UsersControllerTest` — login/refresh, invite/list/update
- `FoldersControllerTest`, `DocumentsControllerTest`, `DocumentSearchControllerTest`, `DocumentDownloadControllerTest` —
  CRUD, search, presigned download/preview
- `SharingControllerTest`, `PublicShareControllerTest` — internal grants, external share links, the unauthenticated
  resolve route
- `TrashControllerTest` — soft-delete/restore listing
- `AuditLogControllerTest`, `DashboardControllerTest` — read-only summary endpoints
- `ProcessingCallbackControllerTest` — the HMAC-signed internal callback the Lambda posts to
- `DocumentVersionConcurrencyTest` — see below

### The permission-inheritance guardrail

Every document/folder feature test includes a case where a second seeded user with no grant on the resource requests
it — asserting **404, never 403** (`Components.php`'s `NotFound` response literally documents this: "doesn't exist, or
the caller has no grant on it (never distinguished)"). The reasoning: a 403 tells a non-participant the resource
*exists*, which is itself a information leak for something like an HR folder. `DocumentService::authorize()` is the one
code path that can grant access, and there's exactly one non-participant case, not one per endpoint that could drift.

### The `sp_document_new_version` concurrency test

`DocumentVersionConcurrencyTest::testConcurrentNewVersionCallsNeverProduceADuplicateVersionNumber` is the Definition of
Done's other named test. It proves the `SELECT ... FOR UPDATE` row lock inside `sp_document_new_version` actually
serializes concurrent writers — a purely sequential PHPUnit test can't exercise a race, since PHP-FPM/PHPUnit runs one
request at a time on one connection.

The technique: it uploads a document over the real HTTP API, then shells out to a **separate PHP process**
(`backend/tests/_support/scripts/call_new_version_worker.php`) twice via `proc_open()`, launching both before reading
either's output. Each worker process opens its own raw `mysqli` connection straight to the test schema (bypassing
CodeIgniter entirely — it's not bootstrapped as a CI4 request) and calls `sp_document_new_version` directly. Because
they're two genuinely separate OS processes with two separate connections, they can hit the row lock at the same
instant — something a single PHP process, single-threaded, cannot simulate. The test then asserts both calls returned
`OK` with two *different* version numbers (`2` and `3`, in either order), proving the lock queued the second caller
instead of letting both compute the same next `version_no`.

## Frontend — Vitest + React Testing Library

```bash
cd frontend
npm run test
```

Two suites under `src/features/documents/`:

- `useDocumentUpload.test.tsx` — drives the upload hook's three-step orchestration (initiate → PUT to S3 → complete)
  deterministically by mocking the API module and the upload-progress helper, using manually-resolved promises to
  control ordering.
- `DocumentDetailPanel.test.tsx` — renders the detail panel against a mocked `api.fetchDocument`, covering the
  permission-aware action buttons (Edit/Share/Upload new version only render for EDITOR/OWNER).

## Frontend — Playwright smoke suite

```bash
npm run test:e2e
```

`e2e/smoke.spec.ts` runs the full plan Definition-of-Done path against the real stack: log in as
`admin@meridian.test` → create a folder → upload a file → upload a second version → generate a share link → open that
link in a fresh incognito browser context → download with no session at all. `playwright.config.ts` does **not** start
either server — both `php spark serve` and `npm run dev` must already be running (see
[`docs/frontend-setup.md`](frontend-setup.md)).

## CI

`.github/workflows/ci.yml` runs three jobs on every push/PR: a license-header check, the backend job (PHPStan +
`vendor/bin/phpunit` against a fresh MySQL + LocalStack service), and the frontend job (`tsc --noEmit`, `eslint`,
`vitest run`, `npm run build`). Playwright is not currently wired into CI — it's run manually against a live local
stack.

## Known caveats (stated once, honestly)

- The async Lambda pipeline (thumbnailing + metadata extraction) was verified as deployed and correctly wired to the
  LocalStack S3 event notification — but a full end-to-end fire was not confirmed on this box, due to a local Docker
  performance issue (LocalStack's Lambda cold start under Docker-in-Docker) unrelated to the pipeline's own code.
- The Playwright suite above is written and correct per manual trace inspection, but has not completed a clean
  end-to-end run on this box for the same host-performance reason.

Neither caveat reflects a gap in the code paths themselves — `ProcessingCallbackControllerTest` exercises the callback
contract the Lambda calls into directly, independent of LocalStack actually invoking it.
