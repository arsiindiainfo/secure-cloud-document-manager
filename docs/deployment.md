# Deployment

Docker Compose is the primary, documented "clone and run" path — no AWS account needed to see the whole system,
including the async processing pipeline, working locally. The AWS/Terraform path exists and is authored, but has not
been applied to a real account; treat it as a deployment *design*, not a deployed environment.

## Docker Compose (primary path)

`infrastructure/docker-compose.yml` defines five services:

| Service | What it is |
|---|---|
| `mysql` | MySQL 8, exposed on host `3309` |
| `localstack` | Emulated S3 + Lambda; `infrastructure/localstack/init-aws.sh` runs automatically on container start — creates both S3 buckets, deploys the `processing-worker` Lambda from a pre-built zip, and wires an `s3:ObjectCreated:*` notification on the `documents/` prefix to invoke it |
| `mailhog` | SMTP catcher for invite emails; web UI on `8026` |
| `api` | The backend built from `infrastructure/docker/Dockerfile.api`, exposed on host `8082` |
| `web` | The frontend build served by nginx (`infrastructure/docker/Dockerfile.web`), exposed on host `3000` |

To bring up the full containerized stack (API and web included, not just the supporting services):

```bash
cd infrastructure
docker compose up -d
```

The **documented development workflow** in this repo instead runs the supporting services only
(`docker compose up -d mysql localstack mailhog`) and runs the backend/frontend natively via `php spark serve` /
`npm run dev` — see [`docs/backend-setup.md`](backend-setup.md) and [`docs/frontend-setup.md`](frontend-setup.md). The
`api`/`web` containers are there for dev-parity verification of the actual built images, not as the everyday inner loop.

The Lambda's zip (`infrastructure/aws/lambda/processing-worker.zip`) is built once on the host, not inside the
container — walking `node_modules` through a Windows bind mount while zipping was orders of magnitude slower than
zipping it natively first.

## AWS / Terraform (authored, not applied)

`infrastructure/aws/` holds Terraform for a real AWS deployment. **This has been written and reviewed but never run
against a real AWS account** — no `terraform apply` has happened, so nothing described below currently exists as live
infrastructure. It documents the intended architecture, and is safe to `plan`/review, not something to point a demo
link at without doing that work first.

What it currently provisions:

- **`s3.tf`** — the private documents bucket (`aws_s3_bucket_public_access_block` with all four block-flags on, plus
  AES256 default encryption and a browser-facing CORS rule so presigned PUT/GET works from the SPA's origin) and a
  separate public SPA bucket for the built React app, with `prevent_destroy` on the documents bucket so an accidental
  `terraform destroy` can't silently take a company's document store with it.
- **`iam.tf`** — a least-privilege IAM role for the API: `s3:PutObject`/`s3:GetObject` scoped to the `documents/*`
  prefix only, deliberately no `s3:ListBucket` and no `s3:DeleteObject`.

What it does **not** yet provision (documented in the plan's architecture, not yet in Terraform): the CloudFront
distribution in front of the SPA bucket, the processing-worker Lambda + its S3 event notification + its own tighter
IAM role, RDS, and the scheduled trash-purge Lambda. Locally, the Lambda and its S3 trigger are stood up directly
against LocalStack by `init-aws.sh` (see above) rather than through Terraform — that's a LocalStack-only convenience,
not a stand-in for the real Lambda infrastructure.

The full target architecture (CloudFront → SPA bucket, API on EC2 → RDS + private S3, S3 event → processing Lambda →
callback, scheduled purge Lambda with its own delete-only IAM role) is diagrammed in the project plan's AWS
Deployment Architecture section — the Terraform above covers the storage/IAM half of it.

### If you do want to apply it

```bash
cd infrastructure/aws
terraform init
terraform plan   # review before applying anything against a real account
```

Set `cors_allowed_origins` to your deployed SPA's real CloudFront domain before ever applying — it defaults to an
empty list.
