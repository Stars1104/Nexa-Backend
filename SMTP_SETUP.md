# SMTP Email Verification Setup Guide

## 📧 SMTP Configuration

Add these environment variables to your `.env` file:

```env
# Basic SMTP Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=your-email@gmail.com
MAIL_FROM_NAME="${APP_NAME}"

# Frontend URL for email verification links
FRONTEND_URL=http://localhost:5000
```

## 🔧 Popular SMTP Providers

### Gmail

```env
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
```

**Setup Steps for Gmail:**

1. Enable 2-Factor Authentication
2. Generate an App Password: Google Account → Security → App passwords
3. Use the App Password (not your regular password)

### Outlook/Hotmail

```env
MAIL_HOST=smtp.office365.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-email@outlook.com
MAIL_PASSWORD=your-password
```

### Yahoo

```env
MAIL_HOST=smtp.mail.yahoo.com
MAIL_PORT=587
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-email@yahoo.com
MAIL_PASSWORD=your-app-password
```

### Mailtrap (for testing)

```env
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_ENCRYPTION=tls
MAIL_USERNAME=your-mailtrap-username
MAIL_PASSWORD=your-mailtrap-password
```

## 🚀 Testing Email Configuration

Run this command to test your SMTP setup:

```bash
php artisan tinker
```

Then test sending an email:

```php
Mail::raw('Test email from Laravel', function ($message) {
    $message->to('test@example.com')
            ->subject('Test Email');
});
```

## 📬 Email Verification Endpoints

### Registration Response

```json
{
    "success": true,
    "message": "Registration successful. Please check your email to verify your account.",
    "token": "1|abcdef123456789...",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "user@example.com",
        "email_verified_at": null,
        "role": "creator",
        "whatsapp": "+1234567890",
        "avatar_url": null,
        "bio": null,
        "company_name": null,
        "student_verified": false,
        "student_expires_at": null,
        "gender": null,
        "state": null,
        "language": "en",
        "created_at": "2023-01-01T00:00:00.000000Z",
        "updated_at": "2023-01-01T00:00:00.000000Z"
    },
    "verification_sent": true
}
```

### Check Verification Status

```bash
GET /api/email/verification-status
Authorization: Bearer YOUR_TOKEN_HERE
```

### Resend Verification Email

```bash
POST /api/email/resend-verification
Authorization: Bearer YOUR_TOKEN_HERE
```

### Email Verification Link

The verification link will be sent to the user's email and should redirect to your frontend:

```
http://localhost:5000/email/verify/{id}/{hash}?expires={timestamp}&signature={signature}
```

## 🔐 Security Notes

1. **Never commit real credentials** to version control
2. **Use App Passwords** for Gmail/Yahoo (not regular passwords)
3. **Set proper CORS headers** for your frontend domain
4. **Use HTTPS** in production
5. **Implement rate limiting** for email sending

## 🐛 Troubleshooting

### Common Issues:

1. **Invalid credentials**

    - Check username/password
    - Use App Password for Gmail/Yahoo

2. **Connection timeout**

    - Check host/port configuration
    - Verify encryption settings

3. **Emails not sending**

    - Check Laravel logs: `tail -f storage/logs/laravel.log`
    - Test with Mailtrap first

4. **Verification links not working**
    - Ensure FRONTEND_URL is set correctly
    - Check if frontend handles the verification route

## 📝 Implementation Complete

Your registration system now includes:

-   ✅ JWT Token generation with Sanctum
-   ✅ Email verification before response
-   ✅ Complete user data return
-   ✅ SMTP configuration ready
-   ✅ Email verification endpoints
-   ✅ Resend verification functionality
-   ✅ Verification status checking

## 🎯 Next Steps

1. Configure your `.env` file with SMTP credentials
2. Test registration endpoint
3. Set up your frontend to handle verification links
4. Implement email verification UI in your frontend
