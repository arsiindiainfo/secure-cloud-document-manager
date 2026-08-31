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

# --------------------------------------------------------------------------
# §9.3 — deploy the processing-worker Lambda and wire it to fire on every
# new object under documents/ (never thumbnails/ — that's this same
# Lambda's own output, and re-triggering on it would loop forever).
# --------------------------------------------------------------------------

LAMBDA_DIR="/opt/code/processing-worker"
LAMBDA_ZIP="/tmp/processing-worker.zip"
FUNCTION_NAME="processing-worker"

if [ -d "${LAMBDA_DIR}" ]; then
  echo "[localstack-init] packaging ${FUNCTION_NAME}..."
  (cd "${LAMBDA_DIR}" && python3 -c "
import shutil
shutil.make_archive('/tmp/processing-worker', 'zip', '.')
")

  awslocal lambda create-function \
    --function-name "${FUNCTION_NAME}" \
    --runtime nodejs20.x \
    --handler index.handler \
    --zip-file "fileb://${LAMBDA_ZIP}" \
    --role arn:aws:iam::000000000000:role/lambda-processing-worker-role \
    --timeout 30 \
    --environment "Variables={API_CALLBACK_URL=${API_CALLBACK_URL:-http://api:8080/api/v1/internal/processing-callback},INTERNAL_HMAC_SECRET=${INTERNAL_HMAC_SECRET:-local-dev-hmac-secret-do-not-use-in-production}}"

  awslocal lambda wait function-active-v2 --function-name "${FUNCTION_NAME}"

  awslocal lambda add-permission \
    --function-name "${FUNCTION_NAME}" \
    --statement-id s3invoke \
    --action lambda:InvokeFunction \
    --principal s3.amazonaws.com \
    --source-arn "arn:aws:s3:::${DOCS_BUCKET}" || true

  FUNCTION_ARN=$(awslocal lambda get-function --function-name "${FUNCTION_NAME}" --query 'Configuration.FunctionArn' --output text)

  awslocal s3api put-bucket-notification-configuration --bucket "${DOCS_BUCKET}" --notification-configuration "{
    \"LambdaFunctionConfigurations\": [
      {
        \"LambdaFunctionArn\": \"${FUNCTION_ARN}\",
        \"Events\": [\"s3:ObjectCreated:*\"],
        \"Filter\": { \"Key\": { \"FilterRules\": [ { \"Name\": \"prefix\", \"Value\": \"documents/\" } ] } }
      }
    ]
  }"

  echo "[localstack-init] ${FUNCTION_NAME} deployed and wired to s3://${DOCS_BUCKET}/documents/*"
else
  echo "[localstack-init] WARNING: ${LAMBDA_DIR} not mounted — skipping processing-worker deployment (§9.3 async processing will not run)"
fi
