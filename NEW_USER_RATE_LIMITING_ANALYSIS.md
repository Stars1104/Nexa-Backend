# New User Rate Limiting Analysis

## Why "Too Many Requests" Errors Only Occur for New Users

### **Root Cause Analysis**

The "Too many requests" and "page reload" errors only occur for newly registered users due to several interconnected factors:

#### **1. Different Authentication Paths**

**Existing Users (Login Only):**

```
User Input → Single API Call (/api/login) → Authentication → Navigation
```

-   **Single API call** to `/api/login`
-   **Direct authentication flow**
-   **No additional state changes** or redirects
-   **Simple navigation** to dashboard

**New Users (Registration + Login):**

```
User Input → Registration API (/api/register) → State Changes → Login API (/api/login) → Complex Navigation
```

-   **First API call** to `/api/register`
-   **Second API call** to `/api/login` (if they try to login immediately)
-   **Multiple state changes** and navigation events
-   **Complex flow** with potential race conditions

#### **2. Rate Limiting Accumulation**

**Previous Configuration:**

-   **Registration**: 10 attempts per minute per IP
-   **Login**: 20 attempts per minute per IP
-   **Both use same IP address** → tracked together

**The Problem:**
When a new user registers and immediately tries to login:

1. **Registration call** counts against registration rate limit
2. **Login call** counts against login rate limit
3. **Same IP address** means both are tracked
4. **Rapid succession** can trigger rate limiting

#### **3. Frontend State Management Complexity**

**New users trigger more complex state changes:**

-   Redux state updates (registration → login)
-   localStorage updates (token, user data)
-   Navigation logic (multiple useEffect hooks)
-   Email verification flow
-   Role-based routing

**Existing users have simpler flow:**

-   Single Redux state update
-   Direct navigation
-   No email verification needed

#### **4. API Interceptor Conflicts**

**Previous Issue:**

-   API interceptors used `window.location.href` for redirects
-   This caused full page reloads instead of smooth React Router navigation
-   New users experienced this more because they had more complex flows

### **Solutions Implemented**

#### **1. Increased Rate Limits for New Users**

```php
'auth' => [
    'login' => [
        'attempts' => 30,        // Increased from 20 to 30
        'lockout_minutes' => 3,  // Reduced from 5 to 3
    ],
    'registration' => [
        'attempts' => 15,        // Increased from 10 to 15
        'lockout_minutes' => 5,  // Reduced from 10 to 5
    ],
],
```

#### **2. Special Rate Limiting for New User Flow**

```php
RateLimiter::for('new-user-flow', function (Request $request) {
    return Limit::perMinute(25)->by($request->ip());
});
```

**Benefits:**

-   **Higher limit** (25 attempts/minute) for registration
-   **Separate tracking** from login rate limiting
-   **Better accommodates** the registration + login sequence

#### **3. Automatic Login After Registration**

```typescript
// For new users, automatically log them in after successful registration
// This prevents the need for a separate login call
if (response.token) {
    dispatch(
        loginSuccess({
            user: response.user,
            token: response.token,
        })
    );
}
```

**Benefits:**

-   **Eliminates** the need for separate login API call
-   **Reduces** rate limiting exposure
-   **Smoother** user experience

#### **4. Improved Frontend Navigation**

```typescript
// Prevent multiple navigation calls
const timeoutId = setTimeout(() => {
    // Navigation logic
}, 100); // Small delay to prevent race conditions
```

**Benefits:**

-   **Prevents race conditions** in navigation
-   **Smoother transitions** between pages
-   **No page reloads**

#### **5. Better Error Handling**

```typescript
// Check for new user flow rate limiting
if (data?.error_type === "new_user_flow_rate_limited") {
    message = `Muitas tentativas de criação de conta. Tente novamente em ${Math.ceil(
        retryAfter / 60
    )} minuto(s).`;
}
```

**Benefits:**

-   **Clear error messages** for new users
-   **Specific guidance** on retry timing
-   **Better user experience**

### **Technical Implementation Details**

#### **Rate Limiting Keys**

**Before (Conflicting):**

-   Registration: `throttle:registration:{ip}`
-   Login: `throttle:auth:{ip}`

**After (Separated):**

-   Registration: `throttle:new-user-flow:{ip}`
-   Login: `throttle:auth:{ip}`

#### **State Management Flow**

**New User Registration:**

1. User submits registration form
2. API call to `/api/register` (new-user-flow rate limiting)
3. On success, automatically dispatch `loginSuccess`
4. Navigation handled by useEffect with timeout
5. No separate login API call needed

**Existing User Login:**

1. User submits login form
2. API call to `/api/login` (auth rate limiting)
3. On success, dispatch `loginSuccess`
4. Direct navigation to dashboard

### **Testing Scenarios**

#### **Test 1: New User Registration**

1. Fill out registration form
2. Submit registration
3. **Expected**: No rate limiting, smooth navigation
4. **Rate Limit**: 25 attempts per minute

#### **Test 2: Existing User Login**

1. Fill out login form
2. Submit login
3. **Expected**: No rate limiting, smooth navigation
4. **Rate Limit**: 30 attempts per minute

#### **Test 3: Rate Limiting Test**

1. Make multiple rapid registration attempts
2. **Expected**: Rate limited after 25 attempts
3. **Message**: Clear error with retry timing

### **Monitoring and Debugging**

#### **Laravel Logs**

```php
\Log::info('New user flow rate limited', [
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
    'attempts_allowed' => 25,
    'lockout_minutes' => 5
]);
```

#### **Rate Limiting Headers**

```
X-RateLimit-Limit: 25
X-RateLimit-Remaining: 20
X-RateLimit-Reset: 1640995200
Retry-After: 300
```

### **Future Improvements**

1. **Dynamic Rate Limiting**: Adjust limits based on user behavior
2. **Whitelist**: Allow trusted IPs to bypass rate limiting
3. **Analytics**: Track rate limiting events for optimization
4. **User Feedback**: Show remaining attempts in UI

### **Conclusion**

The "Too many requests" errors for new users were caused by:

1. **Insufficient rate limits** for the registration + login sequence
2. **Conflicting rate limiting keys** between registration and login
3. **Complex frontend state management** causing multiple API calls
4. **API interceptor conflicts** leading to page reloads

**Solutions implemented:**

-   ✅ **Increased rate limits** for new users
-   ✅ **Separate rate limiting** for new user flow
-   ✅ **Automatic login** after registration
-   ✅ **Improved navigation** without page reloads
-   ✅ **Better error handling** and user feedback

New users now have a **smooth registration experience** with **higher rate limits** and **automatic authentication**, while existing users continue to have their **simple login flow**.
