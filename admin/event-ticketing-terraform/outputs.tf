output "alb_dns_name" {
  description = "Public ALB URL for testing and grading"
  value       = "http://${aws_lb.alb.dns_name}"
}

output "rds_endpoint" {
  description = "Private RDS MySQL Endpoint"
  value       = aws_db_instance.mysql.address
}

output "s3_bucket_name" {
  description = "S3 Bucket Name"
  value       = aws_s3_bucket.assets.id
}