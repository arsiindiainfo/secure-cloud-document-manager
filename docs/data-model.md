# Data Model

MySQL 8, `InnoDB`/`utf8mb4` throughout. Business rules that need a transaction or a row lock live in stored procedures
(`app/Database/Procedures/*.sql`), loaded by the migration `2026-08-29-100011_LoadStoredProcedures.php` — Models call
`CALL sp_xxx(...)` rather than composing raw multi-statement SQL, so locking/transaction logic exists in exactly one
place per operation.

## Table inventory

| Table | Purpose |
|---|---|
| `users` | Accounts: name, email, bcrypt password hash, global role (`ADMIN`/`MANAGER`/`EMPLOYEE`), status |
| `folders` | Hierarchical folders; self-referencing `parent_folder_id`, soft-deletable, unique name among siblings |
| `documents` | Document records (name/description/tags/current version pointer); FULLTEXT-indexed for search |
| `document_versions` | One row per uploaded version of a document — S3 location, mime type, size, checksum, thumbnail key |
| `document_permissions` | Per-resource ACL grants (`VIEWER`/`EDITOR`/`OWNER`) — polymorphic: exactly one of `folder_id`/`document_id` is set |
| `share_links` | External, tokenized, expiring share links with optional download caps |
| `audit_logs` | Append-only log of security-relevant actions, written by the stored procedures themselves |
| `refresh_tokens` | Hashed (never plaintext) JWT refresh tokens, rotated on use |
| `document_processing_jobs` | Status board for the async thumbnail/metadata pipeline, one row per version |
| `trash_retention_settings` | Single-row config: how many days a soft-deleted item stays recoverable |

## ER diagram

```mermaid
erDiagram
    users ||--o{ folders : creates
    users ||--o{ documents : creates
    users ||--o{ document_versions : uploads
    users ||--o{ document_permissions : "granted to"
    users ||--o{ share_links : creates
    users ||--o{ audit_logs : performs
    users ||--o{ refresh_tokens : owns

    folders ||--o{ folders : "parent of"
    folders ||--o{ documents : contains
    folders ||--o{ document_permissions : "grant target"

    documents ||--o{ document_versions : "has versions"
    documents ||--o{ document_permissions : "grant target"
    documents ||--o{ share_links : "shared via"

    document_versions ||--o| document_processing_jobs : "tracked by"

    users {
        bigint id PK
        varchar name
        varchar email
        varchar password_hash
        enum role
        enum status
    }
    folders {
        bigint id PK
        bigint parent_folder_id FK
        varchar name
        bigint created_by FK
        datetime deleted_at
    }
    documents {
        bigint id PK
        bigint folder_id FK
        varchar name
        varchar description
        varchar tags
        int current_version
        bigint created_by FK
        datetime deleted_at
    }
    document_versions {
        bigint id PK
        bigint document_id FK
        int version_no
        varchar s3_bucket
        varchar s3_key
        varchar mime_type
        bigint size_bytes
        char checksum_sha256
        varchar thumbnail_s3_key
        tinyint is_current
        bigint uploaded_by FK
    }
    document_permissions {
        bigint id PK
        bigint folder_id FK
        bigint document_id FK
        bigint user_id FK
        enum permission
        bigint granted_by FK
    }
    share_links {
        bigint id PK
        bigint document_id FK
        char token
        enum permission
        int max_downloads
        int download_count
        datetime expires_at
        datetime revoked_at
        bigint created_by FK
    }
    document_processing_jobs {
        bigint id PK
        bigint document_version_id FK
        enum status
        enum scan_result
        json metadata
        varchar error_message
    }
    audit_logs {
        bigint id PK
        bigint user_id FK
        varchar action
        enum entity_type
        bigint entity_id
        json details
        varchar ip_address
    }
    refresh_tokens {
        bigint id PK
        bigint user_id FK
        char token_hash
        datetime expires_at
        datetime revoked_at
    }
    trash_retention_settings {
        tinyint id PK
        int retention_days
    }
```

Notable constraints straight from the migrations:

- `document_permissions` has a `CHECK` forcing exactly one of `folder_id`/`document_id` to be set, plus separate unique
  keys `(folder_id, user_id)` and `(document_id, user_id)` — one grant per user per resource.
- `document_versions` has `UNIQUE (document_id, version_no)` and is the row `sp_document_new_version` locks with
  `SELECT ... FOR UPDATE` to serialize concurrent version numbering (see [`docs/testing.md`](testing.md)).
- `documents` carries a `FULLTEXT KEY` over `(name, description, tags)`, which `sp_document_search` queries with
  `MATCH ... AGAINST ... IN BOOLEAN MODE`.
- `folders`/`documents`/`users` are all soft-delete (`deleted_at`), never hard-deleted by the API.

## Stored procedure index

### Users & auth
| Procedure | Purpose |
|---|---|
| `sp_user_authenticate` | Looks up a user by email; returns the password hash/role/status for the login flow to verify |
| `sp_user_invite` | Creates a new user (ADMIN-only invite flow) |

### Folders
| Procedure | Purpose |
|---|---|
| `sp_folder_create` | Creates a folder, auto-grants the creator `OWNER`, rejects duplicate sibling names |
| `sp_folder_rename_move` | Renames and/or moves a folder to a new parent |
| `sp_folder_soft_delete` | Soft-deletes a folder (and, per its `p_affected_count` out param, whatever it cascades to) |
| `sp_folder_restore` | Restores a soft-deleted folder — fails if its parent is still deleted |

### Documents & versions
| Procedure | Purpose |
|---|---|
| `sp_document_upload_commit` | Finalizes a completed direct-to-S3 upload into a new `documents` row + first version |
| `sp_document_new_version` | Adds a new version to an existing document; the row-lock-guarded, concurrency-safe one |
| `sp_document_update` | Updates name/description/tags/folder |
| `sp_document_soft_delete` / `sp_document_restore` | Trash lifecycle — idempotent delete, restore blocked if the parent folder is still deleted |
| `sp_document_search` | Builds the caller's visible-document set (direct grants + inherited folder grants, or everything if ADMIN), then filters/sorts/paginates it |

### Sharing
| Procedure | Purpose |
|---|---|
| `sp_document_permission_grant` | Upserts an internal ACL grant (`VIEWER`/`EDITOR`/`OWNER`) on a document |
| `sp_document_permission_revoke` | Removes a grant — refuses to remove the last `OWNER` |
| `sp_share_link_create` | Generates a token-based external share link with an expiry and optional download cap |
| `sp_share_link_consume` | Validates and atomically increments a share link's `download_count`; returns why a link failed (`EXPIRED`/`REVOKED`/`LIMIT_REACHED`/`NOT_FOUND`) |
