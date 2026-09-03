# Portfolio Demo Walkthrough (~5 minutes)

This walks through the seeded "Meridian Consulting Group" dataset step by step, with the real on-screen labels, so you
can follow it literally. It ends by proving two of the project's core claims live: private-by-default storage still
being reachable through a revocable, expiring public link, and folder-level permission inheritance.

## Setup

1. Follow [`docs/backend-setup.md`](backend-setup.md) through migrations, then run both seeders:
   ```bash
   php spark db:seed DemoSeeder
   php spark db:seed MeridianSeeder
   ```
   `MeridianSeeder` creates four folders (**Client Contracts** → **NovaTrail Logistics**, **HR Records**,
   **Compliance**) with six real PDF/DOCX/PNG documents actually uploaded to the LocalStack S3 bucket, plus two
   folder-level grants: MANAGER gets `EDITOR` on **Client Contracts**, EMPLOYEE gets `VIEWER` on **HR Records**.
2. Start the backend (`php spark serve`) and frontend (`npm run dev`) per their setup docs.

## 1. Sign in as the admin

Open `http://localhost:5173/login`. Enter:

- **Email**: `admin@meridian.test`
- **Password**: `Passw0rd!`

Click **Sign in**. You land on `/browse` — the root folder listing, showing **Client Contracts**, **HR Records**, and
**Compliance**.

## 2. Browse to the document and preview it inline

1. Click **Client Contracts**, then **NovaTrail Logistics**.
2. Click **MSA-2026-NovaTrail.pdf**. The document detail panel slides in from the right, on the **details** tab —
   note **Your access: OWNER** (admin created every seeded document).
3. Click the **preview** tab. The PDF renders inline in the panel (an `<iframe>` against a presigned preview URL — no
   file bytes ever pass through your browser's address bar or a public URL; see the README's
   [Private Storage & Signed URL Security](../README.md#private-storage--signed-url-security) section for why that matters).

## 3. Create a share link

1. Back on the **details** tab, click **Share**.
2. Under **External link**, leave the permission as **Download**, leave expiry at **1 day**, and click
   **Generate link**. A read-only field appears with the full `http://localhost:5173/s/<token>` URL and a
   **Copy link** button.
3. Click **Copy link**.

## 4. Open it with no session at all

1. Open a new incognito/private browser window (or a different browser entirely — the point is no cookies, no JWT).
2. Paste the copied link.
3. You land on a standalone page — no app header, no login — showing the document name and file size, a
   **Download** button, and a small **"Secured by Arsi India Info"** footnote.
4. Click **Download**. The file downloads with no authentication step. This is the `share_links` mechanism from the
   README's security write-up: a short-lived signed URL was never handed out directly — this token is explicit,
   revocable (from the Share dialog's link list), and audit-logged, unlike a raw presigned URL would be.

## 5. See folder-level permission inheritance in action

1. Back in the admin session (or sign out and back in), sign out and sign back in as:
   - **Email**: `manager@meridian.test`
   - **Password**: `Passw0rd!`
2. Browse to **Client Contracts** → **NovaTrail Logistics** → **MSA-2026-NovaTrail.pdf**.
3. Note **Your access: EDITOR** — the manager has no *direct* grant on this document; `EDITOR` is inherited from the
   grant `MeridianSeeder` placed on the **Client Contracts** folder itself (§6.3: a folder grant is the floor for
   everything inside it).
4. Because that's `EDITOR` (not just `VIEWER`), the **Edit**, **Share**, and **Upload new version** buttons are all
   present — try **Upload new version** with any file to see it actually take effect (the version counter increments,
   the same `sp_document_new_version` procedure the concurrency test exercises).
5. For contrast, sign out and sign in as `employee@meridian.test` / `Passw0rd!`, browse to the same document. There is
   no grant on **NovaTrail Logistics**, **Client Contracts**, or the document for this account, so the document
   simply never appears in the listing — not a 403, a 404-shaped absence, per the same non-participant guardrail the
   test suite checks (see [`docs/testing.md`](testing.md)).

That's the full loop: private storage end to end, an explicit revocable exception to that privacy (the share link),
and a permission model that resolves correctly through folder inheritance rather than needing a grant on every single
file.
