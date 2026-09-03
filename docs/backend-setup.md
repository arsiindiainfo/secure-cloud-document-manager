# Backend Setup

The API is PHP 8.2 + CodeIgniter 4, backed by MySQL 8 (mostly via stored procedures) and AWS S3 (LocalStack locally). This
guide gets it running with `php spark serve` against Dockerized MySQL/LocalStack/Mailhog — that's the documented local-dev
path, not the `api`/`web` containers in `infrastructure/docker-compose.yml` (those exist for dev-parity testing of the built
image; see [`docs/deployment.md`](deployment.md)).

## Prerequisites

- PHP 8.2+ with the `mysqli` and `intl` extensions
- [Composer](https://getcomposer.org/)
- Docker Desktop (for MySQL, LocalStack, and Mailhog)

## 1. Install dependencies

```bash
cd backend
composer install
```

## 2. Configure environment

```bash
cp .env.example .env
php spark key:generate
```

`.env.example` is the source of truth for every key the app reads — read it before asking "what does this setting do."
A few worth calling out:

- `database.default.*` / `database.tests.*` — point at the Dockerized MySQL below. The test group uses a separate
  `scdm_test` schema so `composer test` never touches your dev data.
- `aws.endpoint` — set to LocalStack (`http://127.0.0.1:4566`) for local dev; leave blank to talk to real AWS.
- `auth.internalHmacSecret` — shared only with the Lambda processing worker; never a user JWT (see the
  `/internal/processing-callback` route).
- `email.SMTPPort` — Mailhog's SMTP port on the host (see step 3).

## 3. Start the Docker services

From `infrastructure/`:

```bash
cd ../infrastructure
docker compose up -d mysql localstack mailhog
```

This starts:

| Service | Host port(s) | Purpose |
|---|---|---|
| `mysql` | `3309` (→ container `3306`) | MySQL 8, schema `scdm` |
| `localstack` | `4566` | Emulated S3 (+ Lambda) — buckets and the processing-worker Lambda are created automatically by `infrastructure/localstack/init-aws.sh` on first boot |
| `mailhog` | `1026` (SMTP), `8026` (web UI) | Catches user-invite emails — open `http://localhost:8026` to read them |

Wait for the healthchecks to pass (`docker compose ps`) before continuing — migrations will fail against a MySQL container
that's still initializing.

## 4. Run migrations

```bash
cd ../backend
php spark migrate --all
```

This runs all 11 migrations under `app/Database/Migrations/`: the 10 application tables, plus
`2026-08-29-100011_LoadStoredProcedures.php`, which loads every `CREATE PROCEDURE` statement in
`app/Database/Procedures/*.sql` (dropping and recreating each one — safe to re-run). There's no separate "load
procedures" step; it's part of `migrate --all`.

## 5. Seed data

```bash
php spark db:seed DemoSeeder
```

Creates the three role accounts (all password `Passw0rd!`):

| Email | Role |
|---|---|
| `admin@meridian.test` | ADMIN |
| `manager@meridian.test` | MANAGER |
| `employee@meridian.test` | EMPLOYEE |

For the full portfolio dataset (folders + real PDF/DOCX/PNG documents uploaded to the LocalStack bucket — see
[`docs/portfolio-demo.md`](portfolio-demo.md)), also run:

```bash
php spark db:seed MeridianSeeder
```

This requires `DemoSeeder`'s users to already exist and LocalStack to be reachable (it calls the real S3 API to upload
each document).

## 6. Run the dev server

```bash
php spark serve
```

Defaults to `http://localhost:8080` — matches `app.baseURL` in `.env.example`. The frontend's `VITE_API_BASE_URL` must
point at `http://localhost:8080/api/v1` for this workflow (see [`docs/frontend-setup.md`](frontend-setup.md)).

Once running:

- `GET http://localhost:8080/api/v1/about` — public project/author metadata
- `http://localhost:8080/api/docs` — the live OpenAPI UI (non-production only)

## 7. Run tests

```bash
composer test
```

This runs `phpunit` (the only script in `composer.json`). Feature tests hit the real stored procedures against the
`scdm_test` schema and the real LocalStack S3 bucket — nothing is mocked. See [`docs/testing.md`](testing.md) for what's
covered and how the concurrency test works.

## Troubleshooting

- **Migrations fail / connection refused** — the MySQL container's healthcheck (`docker compose ps`) needs to report
  `healthy` before `php spark migrate` will succeed; a fresh container can take a few seconds.
- **Uploads/downloads fail with a network error** — confirm LocalStack is up (`http://localhost:4566/_localstack/health`)
  and that `init-aws.sh` created the `scdm-documents-local` bucket (check the `localstack` container logs for
  `[localstack-init] created buckets: ...`).
- **Invite emails never arrive** — they're not supposed to leave the box; check Mailhog's web UI at
  `http://localhost:8026`.
