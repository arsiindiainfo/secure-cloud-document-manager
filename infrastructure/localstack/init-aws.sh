#!/usr/bin/env bash
# Runs automatically on LocalStack container start (mounted to
# /etc/localstack/init/ready.d/). Creates the two buckets the app needs so
# a fresh `docker compose up` is immediately usable — no manual `awslocal`
# setup step for a reviewer cloning the repo.
set -euo pipefail

DOCS_BUCKET="${DOCUMENTS_BUCKET:-scdm-documents-local}"
SPA_BUCKET="${SPA_BUCKET:-scdm-spa-local}"

awslocal s3 mb "s3://${DOCS_BUCKET}"
awslocal s3 mb "s3://${SPA_BUCKET}"

# Documents bucket stays private (Block Public Access is the whole point of
# §10) — CORS is still needed so the browser can PUT/GET directly against
# presigned URLs from the Vite dev server origin.
awslocal s3api put-bucket-cors --bucket "${DOCS_BUCKET}" --cors-configuration '{
  "CORSRules": [
    {
      "AllowedOrigins": ["*"],
      "AllowedMethods": ["GET", "PUT", "HEAD"],
      "AllowedHeaders": ["*"],
      "ExposeHeaders": ["ETag"],
      "MaxAgeSeconds": 3000
    }
  ]
}'

echo "[localstack-init] created buckets: ${DOCS_BUCKET}, ${SPA_BUCKET}"
