# §10 — the one rule this project cannot compromise on: the documents
# bucket has Block Public Access enabled at the bucket level and no bucket
# policy statement ever grants anonymous or wildcard access. There is no
# feature flag or "just for the demo" exception carved out here.

resource "aws_s3_bucket" "documents" {
  bucket = "${var.project}-documents-${var.environment}"

  # A bucket-level deletion safeguard — an accidental `terraform destroy`
  # should not silently take a company's document store with it.
  lifecycle {
    prevent_destroy = true
  }
}

resource "aws_s3_bucket_public_access_block" "documents" {
  bucket = aws_s3_bucket.documents.id

  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_s3_bucket_server_side_encryption_configuration" "documents" {
  bucket = aws_s3_bucket.documents.id

  rule {
    apply_server_side_encryption_by_default {
      sse_algorithm = "AES256"
    }
  }
}

# CORS is required so the *browser* can PUT/GET directly against presigned
# URLs (§10, §21.2) — this is not the same thing as public read access; a
# presigned URL is still required for every request to succeed.
resource "aws_s3_bucket_cors_configuration" "documents" {
  bucket = aws_s3_bucket.documents.id

  cors_rule {
    allowed_methods = ["GET", "PUT", "HEAD"]
    allowed_origins = var.cors_allowed_origins
    allowed_headers = ["*"]
    expose_headers  = ["ETag"]
    max_age_seconds = 3000
  }
}

# --------------------------------------------------------------------------
# The SPA bucket is public-facing by design (§28) — it serves the built
# React app behind CloudFront. This is the one bucket in the project that is
# *meant* to be publicly reachable; it is never used for a single document
# byte.
# --------------------------------------------------------------------------

resource "aws_s3_bucket" "spa" {
  bucket = "${var.project}-spa-${var.environment}"
}

resource "aws_s3_bucket_public_access_block" "spa" {
  bucket = aws_s3_bucket.spa.id

  # Public access is mediated entirely through CloudFront's Origin Access
  # Control (cloudfront.tf) — the bucket itself still blocks direct public
  # reads/ACLs, so "public site" doesn't mean "public bucket".
  block_public_acls       = true
  block_public_policy     = false
  ignore_public_acls      = true
  restrict_public_buckets = false
}
