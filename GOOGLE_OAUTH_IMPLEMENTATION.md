# Google OAuth Implementation

This document describes the Google OAuth implementation for the Nexa application.

## Overview

The Google OAuth implementation allows users to sign up and sign in using their Google accounts. The system supports both creators and brands, with role-based authentication.

## Backend Implementation

### Dependencies

-   Laravel Socialite (v5.21.0)
-   Laravel Sanctum for API authentication

### Configuration

1. **Environment Variables** (add to `.env`):

```env
GOOGLE_CLIENT_ID=your_google_client_id
GOOGLE_CLIENT_SECRET=your_google_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/api/google/callback
```

2. **Google Console Setup**:
    - Go to [Google Cloud Console](https://console.cloud.google.com/)
    - Create a new project or select existing one
    - Enable Google+ API
    - Create OAuth 2.0 credentials
    - Add authorized redirect URIs:
        - `http://localhost:8000/api/google/callback` (development)
        - `https://yourdomain.com/api/google/callback` (production)

### Database Changes

Added new fields to `users` table:

-   `google_id` (string, unique) - Google's unique user ID
-   `google_token` (string, nullable) - OAuth access token
-   `google_refresh_token` (string, nullable) - OAuth refresh token

### API Endpoints

#### 1. Get Google OAuth URL

```
GET /api/google/redirect
```

Returns the Google OAuth authorization URL.

**Response:**

```json
{
    "success": true,
    "redirect_url": "https://accounts.google.com/oauth/authorize?..."
}
```

#### 2. Google OAuth Callback

```
GET /api/google/callback
```

Handles the OAuth callback from Google.

**Response (New User):**

```json
{
    "success": true,
    "token": "1|abc123...",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "creator",
        "avatar_url": "https://example.com/avatar.jpg",
        "student_verified": false,
        "has_premium": false
    },
    "message": "Registration successful"
}
```

**Response (Existing User):**

```json
{
    "success": true,
    "token": "1|abc123...",
    "token_type": "Bearer",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "creator",
        "avatar_url": "https://example.com/avatar.jpg",
        "student_verified": false,
        "has_premium": false
    },
    "message": "Login successful"
}
```

#### 3. Google OAuth with Role Selection

```
POST /api/google/auth
Content-Type: application/json

{
  "role": "creator" // or "brand"
}
```

Handles OAuth with specific role assignment.

### Controller

**File:** `app/Http/Controllers/Auth/GoogleController.php`

Key methods:

-   `redirectToGoogle()` - Returns OAuth URL
-   `handleGoogleCallback()` - Handles OAuth callback
-   `handleGoogleWithRole()` - Handles OAuth with role selection

### User Model Updates

Added Google OAuth fields to fillable array:

```php
protected $fillable = [
    // ... existing fields
    'google_id',
    'google_token',
    'google_refresh_token'
];
```

### Features

1. **Automatic User Creation**: New users are automatically created with Google profile data
2. **Email Verification**: Google users are automatically email verified
3. **Avatar Integration**: Google profile pictures are saved as avatar URLs
4. **Role Assignment**: Supports both creator and brand roles
5. **Token Management**: Stores OAuth tokens for future use
6. **Duplicate Prevention**: Prevents duplicate accounts using Google ID and email

### Security Features

1. **Stateless OAuth**: Uses stateless OAuth flow for API compatibility
2. **Token Validation**: Validates OAuth tokens with Google
3. **Secure Token Storage**: OAuth tokens are stored securely in database
4. **Role Validation**: Validates user roles during authentication

### Testing

Run the Google OAuth tests:

```bash
php artisan test tests/Feature/Auth/GoogleOAuthTest.php
```

## Frontend Integration

The frontend should implement the following flow:

1. User clicks "Sign in with Google"
2. Frontend calls `/api/google/redirect` to get OAuth URL
3. Redirect user to Google OAuth URL
4. Google redirects back to `/api/google/callback`
5. Backend processes OAuth and returns user data
6. Frontend stores authentication token and redirects user

### Example Frontend Flow

```javascript
// 1. Get OAuth URL
const response = await fetch("/api/google/redirect");
const { redirect_url } = await response.json();

// 2. Redirect to Google
window.location.href = redirect_url;

// 3. Handle callback (in your callback route)
const urlParams = new URLSearchParams(window.location.search);
const code = urlParams.get("code");

if (code) {
    // 4. Exchange code for token
    const authResponse = await fetch(`/api/google/callback?code=${code}`);
    const authData = await authResponse.json();

    // 5. Store authentication data
    localStorage.setItem("token", authData.token);
    localStorage.setItem("user", JSON.stringify(authData.user));

    // 6. Redirect to dashboard
    window.location.href = "/dashboard";
}
```

## Error Handling

The system handles various error scenarios:

1. **Invalid OAuth Code**: Returns 422 with error message
2. **Google API Errors**: Catches and returns descriptive error messages
3. **Database Errors**: Handles user creation/update failures
4. **Validation Errors**: Validates required fields and roles

## Production Considerations

1. **HTTPS**: Ensure all OAuth URLs use HTTPS in production
2. **Domain Verification**: Verify your domain in Google Console
3. **Rate Limiting**: Implement rate limiting for OAuth endpoints
4. **Token Refresh**: Implement token refresh logic for long-term sessions
5. **Logging**: Add comprehensive logging for OAuth events
6. **Monitoring**: Monitor OAuth success/failure rates

## Troubleshooting

### Common Issues

1. **"Invalid redirect URI"**: Check Google Console redirect URI configuration
2. **"Client ID not found"**: Verify environment variables are set correctly
3. **"Access denied"**: Check Google+ API is enabled in Google Console
4. **"Token expired"**: Implement token refresh logic

### Debug Mode

Enable debug mode in `.env`:

```env
APP_DEBUG=true
```

This will provide detailed error messages for OAuth issues.

## Security Best Practices

1. **Environment Variables**: Never commit OAuth credentials to version control
2. **HTTPS Only**: Use HTTPS for all OAuth communications
3. **Token Validation**: Always validate OAuth tokens
4. **User Permissions**: Implement proper role-based access control
5. **Session Management**: Use secure session management
6. **Logging**: Log all OAuth events for security monitoring
