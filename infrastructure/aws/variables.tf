variable "project" {
  description = "Short name used to prefix every resource (§28)."
  type        = string
  default     = "secure-cloud-document-manager"
}

variable "environment" {
  description = "Deployment environment name, e.g. production."
  type        = string
  default     = "production"
}

variable "aws_region" {
  type    = string
  default = "us-east-1"
}

variable "cors_allowed_origins" {
  description = "Origins allowed to PUT/GET presigned URLs against the documents bucket — the deployed SPA's CloudFront domain."
  type        = list(string)
  default     = []
}
