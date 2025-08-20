# Rate Limiting Implementation

## Overview

This document describes the rate limiting implementation that has been added to solve the "too many request" error that was occurring when new users registered and logged in.

## Problem Solved

Previously, the application had a global API rate limiting of 1000 requests per minute that was applied to ALL API endpoints, including authentication endpoints. This caused legitimate new users to hit rate limits during registration and login, creating a poor user experience.

## Solution Implemented

### 1. Specific Rate Limiters for Authentication

-   **Login Rate Limiting**: 20 attempts per minute per IP address
-   **Registration Rate Limiting**: 10 attempts per minute per IP address
-   **Password Reset Rate Limiting**: 5 attempts per minute per IP address

### 2. Separate Rate Limiting for Different Endpoints

-   **General API**: 1000 requests per minute per user/IP
-   **Notifications**: 300 requests per minute per user
-   **User Status**: 600 requests per minute per user
-   **Payment**: 100 requests per minute per user

### 3. Configuration-Based Rate Limiting

All rate limiting settings are now configurable through `config/rate_limiting.php`:

```php
'auth' => [
    'login' => [
        'attempts' => 20,        // Login attempts per minute per IP
        'decay_minutes' => 1,    // Time window in minutes
        'lockout_minutes' => 5,  // Lockout duration after max attempts
    ],
    'registration' => [
        'attempts' => 10,        // Registration attempts per minute per IP
        'decay_minutes' => 1,    // Time window in minutes
        'lockout_minutes' => 10, // Lockout duration after max attempts
    ],
    // ... more settings
],
```

## Files Modified

### Backend

1. **`app/Providers/RouteServiceProvider.php`**

    - Added specific rate limiters for auth, registration, and password reset
    - Made rate limiting configurable

2. **`routes/auth.php`**

    - Applied specific rate limiting middleware to authentication routes
    - Removed global API rate limiting from auth endpoints

3. **`routes/api.php`**

    - Applied global API rate limiting only to authenticated user routes
    - Excluded authentication endpoints from global rate limiting

4. **`app/Http/Requests/Auth/LoginRequest.php`**

    - Increased login attempts from 5 to 10 before rate limiting
    - Added better error handling

5. **`app/Http/Middleware/RateLimitHeadersMiddleware.php`** (New)

    - Adds rate limiting headers to responses
    - Provides better client-side rate limiting information

6. **`config/rate_limiting.php`** (New)

    - Centralized configuration for all rate limiting settings

7. **`app/Http/Kernel.php`**
    - Registered the new rate limiting headers middleware

### Frontend

1. **`src/lib/api-error-handler.ts`**

    - Enhanced rate limiting error handling
    - Added specific messages for authentication rate limiting
    - Added retry after time information

2. **`src/api/auth/index.ts`**

    - Improved rate limiting error handling in response interceptor
    - Added specific error messages for auth endpoints

3. **`src/components/auth/AuthErrorHandler.tsx`** (New)
    - Dedicated component for handling authentication errors
    - Better user experience for rate limiting errors

## How It Works

### 1. Route-Level Rate Limiting

Authentication routes now use specific rate limiters instead of the global API rate limiter:

```php
Route::post('/register', [RegisteredUserController::class, 'store'])
    ->middleware('throttle:registration')
    ->name('register');

Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:auth')
    ->name('login');
```

### 2. Request-Level Rate Limiting

The `LoginRequest` class still has its own rate limiting for additional security:

```php
public function ensureIsNotRateLimited(): void
{
    // More lenient rate limiting for login attempts (10 attempts per 5 minutes)
    if (! RateLimiter::tooManyAttempts($this->throttleKey(), 10)) {
        return;
    }
    // ... error handling
}
```

### 3. Rate Limiting Headers

Responses now include helpful headers:

```
X-RateLimit-Limit: 20
X-RateLimit-Remaining: 15
X-RateLimit-Reset: 1640995200
Retry-After: 300
```

## Benefits

1. **Better User Experience**: New users can register and login without hitting rate limits
2. **Security**: Still prevents brute force attacks and spam
3. **Configurability**: Easy to adjust rate limiting settings
4. **Transparency**: Clear error messages and retry information
5. **Separation of Concerns**: Different rate limits for different types of endpoints

## Testing

Run the rate limiting tests to verify functionality:

```bash
php artisan test tests/Feature/RateLimitingTest.php
```

## Configuration

To adjust rate limiting settings, modify `config/rate_limiting.php`:

```php
'auth' => [
    'login' => [
        'attempts' => 30,        // Increase to 30 attempts per minute
        'lockout_minutes' => 3,  // Reduce lockout to 3 minutes
    ],
],
```

## Monitoring

Rate limiting events are logged and can be monitored through:

-   Laravel logs
-   Rate limiting headers in responses
-   Frontend error handling and user feedback

## Future Improvements

1. **Dynamic Rate Limiting**: Adjust limits based on user behavior
2. **Whitelist**: Allow trusted IPs to bypass rate limiting
3. **Analytics**: Track rate limiting events for optimization
4. **User Feedback**: Show remaining attempts in UI

## Troubleshooting

### Common Issues

1. **Still getting rate limited**: Check if the route has the correct middleware
2. **Headers not showing**: Verify the RateLimitHeadersMiddleware is registered
3. **Configuration not working**: Clear config cache with `php artisan config:clear`

### Debug Mode

Enable debug logging for rate limiting:

```php
// In .env
RATE_LIMITING_DEBUG=true
```

This will log rate limiting decisions to help troubleshoot issues.
