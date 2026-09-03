# Frontend Setup

React 19 + TypeScript + Vite, styled with Tailwind CSS, server state via React Query, forms via React Hook Form + Zod.
Uploads go directly from the browser to S3 against a presigned URL the backend hands out — the frontend never proxies
file bytes through itself.

## Prerequisites

- Node.js 20+
- The backend running per [`docs/backend-setup.md`](backend-setup.md) — this app has no mock-API mode; every screen
  talks to a real API.

## 1. Install dependencies

```bash
cd frontend
npm install
```

## 2. Configure environment

```bash
cp .env.example .env
```

The only variable is `VITE_API_BASE_URL`. For the documented local-dev workflow (`php spark serve` on its default port),
set it to:

```
VITE_API_BASE_URL=http://localhost:8080/api/v1
```

Point it at whichever port the backend is actually listening on — if you're running the Dockerized `api` container
instead of `php spark serve` (see [`docs/deployment.md`](deployment.md)), that's `http://localhost:8082/api/v1` instead.

## 3. Run the dev server

```bash
npm run dev
```

Vite's default dev server is `http://localhost:5173` — this is also the origin Playwright's `baseURL` expects (see
below) and the origin the backend's `app.frontendUrl` uses to build public share-link URLs.

## 4. Build for production

```bash
npm run build
```

Runs `tsc -b && vite build` — a type-check followed by the Vite production build.

## 5. Run unit tests (Vitest)

```bash
npm run test
```

Runs `vitest run` against the two component/hook tests under `src/features/documents/` (`DocumentDetailPanel.test.tsx`,
`useDocumentUpload.test.tsx`). Both mock the API layer directly (`vi.mock('./api')`) rather than using MSW at the
network layer, so no server needs to be running for this command.

## 6. Run the end-to-end smoke test (Playwright)

```bash
npm run test:e2e
```

Unlike the unit tests, **this needs both the backend and the frontend dev server already running against real data** —
`playwright.config.ts` does not start either one itself, it only points `baseURL` at `http://localhost:5173`. Before
running it:

1. Backend up per `docs/backend-setup.md` (migrated + `DemoSeeder` applied) and serving on the port your `.env` points at.
2. `npm run dev` running in another terminal.

The suite (`e2e/smoke.spec.ts`) logs in as `admin@meridian.test`, creates a folder, uploads a file, uploads a second
version, generates a share link, and opens that link in a fresh incognito browser context to confirm the download works
with no session at all. See [`docs/testing.md`](testing.md) for the full breakdown and known caveats.

## Other scripts

| Script | What it runs |
|---|---|
| `npm run lint` | `eslint .` |
| `npm run preview` | Serves the production build locally |
