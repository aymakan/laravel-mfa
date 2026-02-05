# Laravel MFA Package

A production-grade, API-only Multi-Factor Authentication (MFA) enforcement package for Laravel. This package provides a clean, middleware-driven MFA layer that works **after** successful email/password login.

## Features

- **TOTP-based MFA** using RFC 6238 (compatible with Google Authenticator, Authy, etc.)
- **API-only** — no Blade views, no redirects; JSON responses only
- **Middleware-driven** — does not replace Laravel authentication
- **Pluggable architecture** — ready for future SMS/Email/Push drivers
- **Multiple verification stores** — session, cache, or database (see [Verification state storage](#verification-state-storage))
- **Security-first** — OTP replay protection, rate limiting, fail-closed design
- **Config-driven** — fully customizable via published config
- **Enforcement options** — global (`required` / `optional`) or per-user (e.g. `mfa_required` attribute)

## Requirements

- PHP 8.2+
- Laravel 10+ / 11+ / 12+
- Laravel Sanctum (or similar API auth with Bearer token)

## Installation

```bash
composer require aymakan/laravel-mfa
```

Publish config and migrations:

```bash
php artisan vendor:publish --tag=mfa-config
php artisan vendor:publish --tag=mfa-migrations
php artisan migrate
```

## Basic setup

### 1. Add the HasMfa trait to your User model

```php
use Aymakan\Mfa\Traits\HasMfa;

class User extends Authenticatable
{
    use HasMfa;

    // Optional: add to $fillable if using per-user enforcement
    protected $fillable = ['email', 'password', 'mfa_required'];

    protected $casts = [
        'mfa_required' => 'boolean',
    ];
}
```

### 2. Apply the middleware to protected routes

```php
// routes/api.php (or your API route file)

Route::middleware(['auth:sanctum', 'mfa.verified'])->group(function () {
    Route::get('/user', [UserController::class, 'show']);
    Route::get('/dashboard', [DashboardController::class, 'index']);
    // ... other protected routes
});
```

MFA routes (status, verify, enroll) are **not** protected by `mfa.verified`; they only require auth. Keep your main app routes behind `mfa.verified`.

## API endpoints

The package registers these routes under the configured prefix (default: `mfa`, so full paths are e.g. `/mfa/status`):

| Method   | Path               | Description                        |
|----------|--------------------|------------------------------------|
| GET      | `/{prefix}/status` | MFA status for authenticated user |
| POST     | `/{prefix}/verify` | Verify MFA code (login flow)      |
| POST     | `/{prefix}/enroll/start`  | Start MFA enrollment        |
| POST     | `/{prefix}/enroll/confirm`| Confirm enrollment with OTP  |
| DELETE   | `/{prefix}/enroll/cancel` | Cancel pending enrollment   |
| DELETE   | `/{prefix}/disable`      | Disable MFA (requires OTP)  |

`/verify` and `/enroll/confirm` and `/disable` use the `throttle:mfa` rate limiter.

## Usage examples

### Check MFA status

```http
GET /mfa/status
Authorization: Bearer {token}
```

Response:

```json
{
    "data": {
        "mfa_enabled": true,
        "mfa_verified": true,
        "mfa_type": "totp",
        "mfa_required": true,
        "verified_at": "2026-02-02T12:39:42+00:00"
    }
}
```

`verified_at` is present only when `mfa_verified` is true.

### Enroll in MFA

**Step 1: Start enrollment**

```http
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

**Step 2:** Generate a QR code from `provisioning_uri` (e.g. with a frontend library or `https://api.qrserver.com`).

**Step 3: Confirm enrollment**

```http
POST /mfa/enroll/confirm
Authorization: Bearer {token}
Content-Type: application/json

{"code": "123456"}
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

### Verify MFA (login flow)

After login, if protected routes return `403` with `MFA_REQUIRED`, call verify with the user’s OTP:

```http
POST /mfa/verify
Authorization: Bearer {token}
Content-Type: application/json

{"code": "123456"}
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

```http
DELETE /mfa/disable
Authorization: Bearer {token}
Content-Type: application/json

{"code": "123456"}
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

## Middleware behaviour

When `mfa.verified` is applied:

1. If MFA is globally disabled (`enabled` = false) → pass through.
2. If the user is not authenticated → pass through (let auth middleware handle it).
3. **Enforcement:**
   - If the user **must** complete MFA (`enforcement` = `required` or per-user attribute e.g. `mfa_required` = true): they must have MFA enabled and verified; otherwise → **403** `MFA_REQUIRED`.
   - If MFA is **optional** for this user: only users who already have MFA enabled must verify; others pass.
4. If the user has MFA enabled and is verified → pass through.
5. If the user has MFA enabled but not verified → **403** `MFA_REQUIRED`.

The `mfa_required` field in `GET /mfa/status` indicates whether this user must complete MFA (enroll and/or verify).

Error response when MFA is required but not satisfied:

```json
{
    "error": {
        "code": "MFA_REQUIRED",
        "message": "Multi-factor authentication is required."
    }
}
```

## Verification state storage

Verification state (“this user has passed MFA for this session / period”) can be stored in three ways:

| Store      | Config value  | Use when |
|-----------|----------------|----------|
| **Session** | `session` (default) | Frontend sends session cookies (stateful Sanctum). Verification lives for the session. |
| **Cache**   | `cache`        | Bearer-only API; no session. Verification is keyed by user id and TTL (e.g. 8 hours). |
| **Database**| `database`     | Bearer-only API; survives cache flush. Uses `mfa_verifications` table and TTL. |

Set in config:

```php
'verification' => [
    'store' => env('MFA_VERIFICATION_STORE', 'session'), // 'session' | 'cache' | 'database'

    // Session (when store === 'session')
    'session_key' => 'mfa_verified_at',
    'lifetime' => null, // null = session lifetime

    // Cache (when store === 'cache')
    'key_prefix' => 'mfa_verified_at',
    'lifetime' => 480, // minutes (e.g. 8 hours)

    // Database (when store === 'database'); run mfa migrations
    'table' => 'mfa_verifications',
    'lifetime' => 480,
],
```

For SPAs using only Bearer tokens (no session cookies), use `cache` or `database` so verification persists across requests.

## Configuration options

```php
// config/mfa.php

return [
    'enabled' => env('MFA_ENABLED', true),

    // 'optional' = only users who have MFA enabled must verify
    // 'required' = every user must enroll and verify
    'enforcement' => env('MFA_ENFORCEMENT', 'optional'),

    // Per-user attribute (e.g. mfa_required). When true, that user must complete MFA
    // even when enforcement is 'optional'. Set to null to disable.
    'enforcement_per_user_attribute' => env('MFA_ENFORCEMENT_PER_USER_ATTRIBUTE'),

    'default' => env('MFA_DRIVER', 'totp'),

    'drivers' => [
        'totp' => [
            'issuer' => env('MFA_TOTP_ISSUER', config('app.name')),
            'digits' => 6,
            'period' => 30,
            'algorithm' => 'sha1',
            'window' => 1,
        ],
    ],

    'routes' => [
        'prefix' => 'mfa',
        'middleware' => ['api', 'auth:sanctum'],
    ],

    'middleware_alias' => 'mfa.verified',

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
            'message' => 'Too many verification attempts. Please try again later.',
        ],
    ],

    'rate_limit' => [
        'max_attempts' => 5,
        'decay_minutes' => 5,
    ],

    'verification' => [
        'store' => env('MFA_VERIFICATION_STORE', 'session'),
        'session_key' => 'mfa_verified_at',
        'lifetime' => null,
        'key_prefix' => 'mfa_verified_at',
        'table' => 'mfa_verifications',
    ],

    'user' => [
        'model' => null, // or \App\Models\User::class
        'foreign_key' => 'user_id',
    ],
];
```

## Using the facade

```php
use Aymakan\Mfa\Facades\Mfa;

$hasMfa = Mfa::userHasMfaEnabled($user);
$valid = Mfa::verify($user, '123456');
$uri = Mfa::getProvisioningUri($user);
Mfa::disable($user);
```

## Security

- **No OTP reuse** — replay protection for verification attempts.
- **Rate limiting** — `throttle:mfa` on verify, confirm, and disable (configurable in `rate_limit`).
- **Fail closed** — if MFA state is unclear, access is denied.
- **Logout** — verification state is cleared on `Illuminate\Auth\Events\Logout`.

## Testing

```bash
composer test
```

## License

MIT License
