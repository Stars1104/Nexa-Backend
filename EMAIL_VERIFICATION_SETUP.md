# Email Verification Setup with AWS SES

This guide explains how to set up email verification using AWS SES for the Nexa platform.

## Environment Variables

Add the following environment variables to your `.env` file:

```bash
# AWS SES Configuration
AWS_ACCESS_KEY_ID=your_aws_access_key_id
AWS_SECRET_ACCESS_KEY=your_aws_secret_access_key
AWS_DEFAULT_REGION=us-east-1
AWS_SES_REGION=us-east-1

# Mail Configuration
MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@nexa.com
MAIL_FROM_NAME="Nexa Platform"

# Email Verification Settings
EMAIL_VERIFICATION_EXPIRE=60
EMAIL_VERIFICATION_RESEND_THROTTLE=6,1

# Frontend URL
FRONTEND_URL=http://localhost:3000
```

## AWS SES Setup

1. **Create AWS Account**: If you don't have one, create an AWS account
2. **Access SES**: Go to AWS SES (Simple Email Service) console
3. **Verify Domain**: Verify your domain or use sandbox mode for testing
4. **Create IAM User**: Create an IAM user with SES permissions
5. **Get Credentials**: Get the Access Key ID and Secret Access Key

### IAM Policy for SES

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": ["ses:SendEmail", "ses:SendRawEmail"],
            "Resource": "*"
        }
    ]
}
```

## Testing

1. **Sandbox Mode**: Initially, SES runs in sandbox mode (limited to verified emails)
2. **Production Access**: Request production access to send to any email address
3. **Email Limits**: Check your SES sending limits in the AWS console

## How It Works

1. **Registration**: User registers and receives verification email
2. **Email Sent**: AWS SES sends verification email with signed link
3. **Verification**: User clicks link and is redirected to frontend
4. **Backend Verification**: Frontend calls backend to verify email
5. **Account Activation**: User account is activated and can log in

## Troubleshooting

### Common Issues

1. **Email Not Sent**: Check AWS credentials and SES configuration
2. **Verification Link Expired**: Links expire after 60 minutes by default
3. **Invalid Signature**: Ensure proper URL signing and parameter handling

### Debug Steps

1. Check Laravel logs: `tail -f storage/logs/laravel.log`
2. Verify AWS credentials are correct
3. Check SES sending limits and sandbox status
4. Test email sending with Laravel Tinker

## Security Considerations

1. **Signed URLs**: Verification links are signed to prevent tampering
2. **Rate Limiting**: Resend verification is rate-limited
3. **Expiration**: Links expire after a configurable time
4. **HTTPS**: Always use HTTPS in production

## Customization

You can customize the email template by modifying:

-   `app/Notifications/VerifyEmailNotification.php`
-   Email content and styling
-   Verification link expiration time
-   Rate limiting settings
