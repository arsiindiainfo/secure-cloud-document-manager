# API Reference

The full, generated endpoint reference lives at **`/api/docs`** (Swagger UI) and **`/api/docs.json`** (the raw OpenAPI
document), served by `OpenApiController` and built from `zircote/swagger-php` attributes on every controller
(non-production only — see `app/OpenApi/OpenApiInfo.php`). This page is a companion, not a duplicate: it's about how
to *read* that list, not a restatement of it.

## Base path & auth

All authenticated routes live under `/api/v1`. Every route requires `Authorization: Bearer <accessToken>` **except**:

- `POST /auth/login`, `POST /auth/refresh` — no token yet
- `GET /s/:token` — the public share-link resolve route; deliberately short and unversioned since it's handed out in
  already-sent links and must never break
- `POST /internal/processing-callback` — requires `X-Signature`, an HMAC-SHA256 of the raw request body keyed with a
  secret shared only with the Lambda worker; a user JWT is never accepted here

## Response envelope

Every response is exactly one of three shapes, built by `ApiResponseTrait` (`backend/app/Traits/ApiResponseTrait.php`)
— no controller builds a response body by hand.

**Single resource** (`ok()` / `created()`):
```json
{ "success": true, "data": { "id": 482, "name": "MSA-2026-NovaTrail.pdf", "currentVersion": 2 } }
```

**Paginated list** (`paginated()`):
```json
{
  "success": true,
  "data": [ /* array of resources */ ],
  "meta": { "page": 1, "limit": 20, "total": 86, "totalPages": 5 }
}
```

**Error** — every failure is thrown as an `ApiException` subclass (`backend/app/Exceptions/ApiException.php`) and
rendered here, never assembled ad hoc at the call site:
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Request payload failed validation.",
    "details": [ { "field": "name", "message": "name is required" } ]
  }
}
```
`details` is omitted entirely (not `null`) when there's nothing structured to attach — `ApiException::getResponse()`
filters it out with `array_filter`.

## The "404, never 403" guardrail

For any document- or folder-scoped route, a request from a user with **no grant at all** on that resource returns
**404** — the same response shape and status as the resource simply not existing. It is never a 403. The `NotFound`
shared response (`app/OpenApi/Components.php`) documents this directly: "doesn't exist, or the caller has no grant on
it (never distinguished)."

This is deliberate, not an oversight: a 403 would itself leak information — it would tell a non-participant that a
specific document ID *exists* (e.g. an HR record they were never meant to know about), even though they can't open
it. Returning 404 either way means the only thing an unauthorized caller ever learns is "not available to you,"
identical to "not there." Every document/folder feature test includes this exact case — see
[`docs/testing.md`](testing.md).

`403` (the `ForbiddenRole` response) is reserved for a different situation: the caller is authenticated and the
resource *is* something they can see the existence of, but their **global role** lacks the capability outright — e.g.
a non-ADMIN calling a `/users` management endpoint.

## Pagination shape

List endpoints accept `page`/`limit` query parameters and return the `meta` block shown above. `totalPages` is
computed server-side (`ceil(total / limit)`) — clients should read it rather than recomputing it, since `limit` can
be `0` in edge cases (`meta.totalPages` is `0` then, not a division error).

## Naming conventions worth knowing before you skim the endpoint list

- Resources are plural nouns (`/documents`, `/folders`); non-CRUD actions are sub-resource verbs, never a verb in the
  base path: `POST /documents/:id/restore`, `POST /documents/:id/versions/initiate`.
- Response payloads return only what the corresponding screen renders — no raw database rows, no S3 internals beyond
  what a signed-URL response actually needs to hand back.
- The three-step upload flow (`uploads/initiate` → browser `PUT`s directly to S3 → `uploads/complete`) and its
  per-version equivalent (`versions/initiate` / `versions/complete`) are the one place a "verb" pair looks unusual —
  see the README's [Private Storage & Signed URL Security](../README.md#private-storage--signed-url-security) section
  for why the upload is split into three steps instead of one.
