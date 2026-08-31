# §5 / §10 — the API's IAM role is scoped to put/get on one bucket prefix
# only: no s3:ListBucket, no s3:DeleteObject. A stolen API credential could
# still mint presigned URLs for objects it already knows the key of, but it
# could never enumerate the bucket or delete anything in it. The Lambda
# roles (added in the async-processing infra, §9.3/§28) are scoped even
# tighter and are the *only* principals ever granted s3:DeleteObject.

data "aws_iam_policy_document" "api_assume_role" {
  statement {
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ec2.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "api" {
  name               = "${var.project}-api-${var.environment}"
  assume_role_policy = data.aws_iam_policy_document.api_assume_role.json
}

data "aws_iam_policy_document" "api_documents_access" {
  statement {
    sid    = "PresignPutGetOnly"
    effect = "Allow"
    actions = [
      "s3:PutObject",
      "s3:GetObject",
    ]
    resources = ["${aws_s3_bucket.documents.arn}/documents/*"]
  }

  # Deliberately no statement grants s3:ListBucket or s3:DeleteObject on
  # this bucket to this role — see the file header.
}

resource "aws_iam_policy" "api_documents_access" {
  name   = "${var.project}-api-documents-access-${var.environment}"
  policy = data.aws_iam_policy_document.api_documents_access.json
}

resource "aws_iam_role_policy_attachment" "api_documents_access" {
  role       = aws_iam_role.api.name
  policy_arn = aws_iam_policy.api_documents_access.arn
}

resource "aws_iam_instance_profile" "api" {
  name = "${var.project}-api-${var.environment}"
  role = aws_iam_role.api.name
}
