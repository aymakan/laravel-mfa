# Laravel MFA Package

A production-grade, API-only Multi-Factor Authentication (MFA) enforcement package for Laravel. This package provides a clean, middleware-driven MFA layer that works **after** successful email/password login.

## Features

- **TOTP-based MFA** using RFC 6238 (compatible with Google Authenticator, Authy, etc.)
- **API-only** - no Blade views, no redirects, JSON responses only
- **Middleware-driven** - doesn't replace Laravel authentication
- **Pluggable architecture** - ready for future SMS/Email/Push drivers
- **Session-based verification** - works with Laravel Sanctum
- **Security-first** - OTP replay protection, rate limiting, fail-closed design
- **Config-driven** - fully customizable via published config

## Requirements

- PHP 8.2+
- Laravel 10+ / 11+ / 12+
- Laravel Sanctum (or similar session-based auth)

## Installation

```bash
composer require aymakan/laravel-mfa
```

## Configuration

Publish the config and migrations:

```bash
php artisan vendor:publish --tag=mfa-config
php artisan vendor:publish --tag=mfa-migrations
php artisan migrate
```

## Basic Setup

### 1. Add the HasMfa trait to your User model (optional but recommended)

```php
use Aymakan\Mfa\Traits\HasMfa;

class User extends Authenticatable
{
    use HasMfa;
    
    // ...
}
```

### 2. Apply the middleware to protected routes

```php
// routes/api.php

Route::middleware(['auth:sanctum', 'mfa.verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class);
    Route::get('/profile', ProfileController::class);
    // ... other protected routes
});
```

## API Endpoints

The package registers these routes (default prefix: `/mfa`):

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/mfa/status` | Get MFA status for authenticated user |
| POST | `/mfa/verify` | Verify MFA code (login flow) |
| POST | `/mfa/enroll/start` | Start MFA enrollment |
| POST | `/mfa/enroll/confirm` | Confirm enrollment with OTP |
| DELETE | `/mfa/enroll/cancel` | Cancel pending enrollment |
| DELETE | `/mfa/disable` | Disable MFA (requires OTP) |

## Usage Examples

### Check MFA Status

```bash
GET /mfa/status
Authorization: Bearer {token}
```

Response:
```json
{
    "data": {
        "mfa_enabled": true,
        "mfa_verified": false,
        "mfa_type": "totp",
        "mfa_required": true
    }
}
```

### Enroll in MFA

**Step 1: Start enrollment**

```bash
POST /mfa/enroll/start
Authorization: Bearer {token}
```

Response:
```json
{
    "data": {
        "provisioning_uri": "otpauth://totp/MyApp:user@example.com?secret=JBSWY3DPEHPK3PXP&issuer=MyApp",
        "type": "totp",
        "message": "Scan the QR code with your authenticator app, then confirm with a code."
    }
}
```

**Step 2: Generate QR code from provisioning_uri (frontend)**

Use a library like `qrcode` to display the QR code to the user.

**Step 3: Confirm enrollment**

```bash
POST /mfa/enroll/confirm
Authorization: Bearer {token}
Content-Type: application/json

{
    "code": "123456"
}
```

Response:
```json
{
    "data": {
        "enabled": true,
        "message": "MFA has been enabled successfully."
    }
}
```

### Verify MFA (Login Flow)

After successful login, if the middleware returns `MFA_REQUIRED`:

```bash
POST /mfa/verify
Authorization: Bearer {token}
Content-Type: application/json

{
    "code": "123456"
}
```

Response:
```json
{
    "data": {
        "verified": true,
        "message": "MFA verification successful."
    }
}
```

### Disable MFA

```bash
DELETE /mfa/disable
Authorization: Bearer {token}
Content-Type: application/json

{
    "code": "123456"
}
```

Response:
```json
{
    "data": {
        "disabled": true,
        "message": "MFA has been disabled."
    }
}
```

## Middleware Behavior

When the `mfa.verified` middleware is applied:

1. If MFA is globally disabled → pass through
2. If user is not authenticated → pass through (let auth middleware handle)
3. **Enforcement rules:**
   - **Required for this user** (global `enforcement` = `required` or per-user attribute set): user must have MFA enabled and verified; if not enrolled or not verified → 403 `MFA_REQUIRED`
   - **Optional for this user**: only users who have MFA enabled must verify; others pass through
4. If user has MFA enabled AND verified → pass through
5. If user has MFA enabled but NOT verified → return 403 with `MFA_REQUIRED`

The `mfa_required` field in GET `/mfa/status` reflects whether this user must complete MFA (enroll and/or verify).

```json
{
    "error": {
        "code": "MFA_REQUIRED",
        "message": "Multi-factor authentication is required."
    }
}
```

## Configuration Options

```php
// config/mfa.php

return [
    // Global toggle
    'enabled' => env('MFA_ENABLED', true),

    // Enforcement: 'optional' = only users who have MFA enabled must verify
    // 'required' = every user must enroll and verify
    'enforcement' => env('MFA_ENFORCEMENT', 'optional'),

    // Per-user attribute (e.g. mfa_required). When true, that user must complete MFA
    // even when enforcement is 'optional'. Set to null to disable.
    'enforcement_per_user_attribute' => env('MFA_ENFORCEMENT_PER_USER_ATTRIBUTE'),

    // Default driver
    'default' => env('MFA_DRIVER', 'totp'),

    // Driver-specific options
    'drivers' => [
        'totp' => [
            'issuer' => env('MFA_TOTP_ISSUER', config('app.name')),
            'digits' => 6,
            'period' => 30,
            'algorithm' => 'sha1',
            'window' => 1,
        ],
    ],

    // Route configuration
    'routes' => [
        'prefix' => 'mfa',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    // Middleware alias
    'middleware_alias' => 'mfa.verified',

    // Error responses
    'errors' => [
        'mfa_required' => [
            'status' => 403,
            'code' => 'MFA_REQUIRED',
            'message' => 'Multi-factor authentication is required.',
        ],
        'mfa_invalid' => [
            'status' => 422,
            'code' => 'MFA_INVALID',
            'message' => 'Invalid verification code.',
        ],
        'mfa_rate_limited' => [
            'status' => 429,
            'code' => 'MFA_RATE_LIMITED',
            'message' => 'Too many verification attempts.',
        ],
    ],

    // Rate limiting
    'rate_limit' => [
        'max_attempts' => 5,
        'decay_minutes' => 5,
    ],

    // Verification state
    'verification' => [
        'session_key' => 'mfa_verified_at',
        'lifetime' => null, // null = session lifetime
    ],
];
```

## Using the Facade

```php
use Aymakan\Mfa\Facades\Mfa;

// Check if user has MFA enabled
$hasMfa = Mfa::userHasMfaEnabled($user);

// Verify an OTP
$valid = Mfa::verify($user, '123456');

// Get provisioning URI for QR code
$uri = Mfa::getProvisioningUri($user);

// Disable MFA
Mfa::disable($user);
```

## Security Considerations

- **No OTP reuse**: Each OTP can only be used once (replay protection)
- **Rate limiting**: Verification attempts are rate-limited per user
- **Time-based only**: No counter-based fallbacks
- **Fail closed**: If MFA state is unclear, access is denied
- **Session binding**: MFA verification is tied to the user session
- **Logout clearing**: MFA verification state is cleared on logout

## Testing

```bash
composer test
```

## License

MIT License
