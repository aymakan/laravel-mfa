<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | MFA Enabled
    |--------------------------------------------------------------------------
    |
    | Global toggle for MFA enforcement. When disabled, the middleware will
    | always pass through without checking MFA status.
    |
    */
    'enabled' => env('MFA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | MFA Enforcement
    |--------------------------------------------------------------------------
    |
    | 'optional' = only users who have MFA enabled must verify; others pass.
    | 'required' = every user must have MFA enabled and verified (must enroll).
    |
    | When enforcement_per_user_attribute is set, that attribute on the user
    | model (e.g. mfa_required) is checked. If true, that user must complete
    | MFA (enroll + verify) even when enforcement is 'optional'.
    |
    */
    'enforcement' => env('MFA_ENFORCEMENT', 'optional'),

    'enforcement_per_user_attribute' => env('MFA_ENFORCEMENT_PER_USER_ATTRIBUTE'),

    /*
    |--------------------------------------------------------------------------
    | Default MFA Driver
    |--------------------------------------------------------------------------
    |
    | The default driver to use for MFA verification. Currently only 'totp'
    | is supported, but the architecture allows for future drivers like
    | 'sms', 'email', or 'push'.
    |
    */
    'default' => env('MFA_DRIVER', 'totp'),

    /*
    |--------------------------------------------------------------------------
    | MFA Drivers
    |--------------------------------------------------------------------------
    |
    | Configuration for each MFA driver. Each driver has its own settings.
    |
    */
    'drivers' => [
        'totp' => [
            // The issuer name shown in authenticator apps
            'issuer' => env('MFA_TOTP_ISSUER', config('app.name', 'Laravel')),

            // Number of digits in the OTP (6 or 8)
            'digits' => 6,

            // Time step in seconds (default: 30)
            'period' => 30,

            // Algorithm used for TOTP (sha1, sha256, sha512)
            'algorithm' => 'sha1',

            // Window of time steps to check (helps with clock drift)
            // Value of 1 means check current, previous, and next time step
            'window' => 1,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the MFA routes prefix and middleware stack.
    |
    */
    'routes' => [
        // URL prefix for MFA routes
        'prefix' => 'mfa',

        // Middleware applied to MFA routes (must include auth middleware)
        'middleware' => ['api', 'auth:sanctum'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware Alias
    |--------------------------------------------------------------------------
    |
    | The alias used to reference the MFA middleware in route definitions.
    | Usage: Route::middleware('mfa.verified')->group(...)
    |
    */
    'middleware_alias' => 'mfa.verified',

    /*
    |--------------------------------------------------------------------------
    | Error Responses
    |--------------------------------------------------------------------------
    |
    | Customize error responses returned by the middleware.
    |
    */
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

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for MFA verification attempts.
    |
    */
    'rate_limit' => [
        // Maximum attempts before lockout
        'max_attempts' => 5,

        // Lockout duration in minutes
        'decay_minutes' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Verification State
    |--------------------------------------------------------------------------
    |
    | Configure how MFA verification state is stored and managed.
    |
    | Store driver: 'session' (per-request session, requires cookies),
    | 'cache' (per-user in cache, works with Bearer-only / no session), or
    | 'database' (per-user in DB, survives cache clear; recommended for Bearer).
    |
    */
    'verification' => [
        'store' => env('MFA_VERIFICATION_STORE', 'session'),

        // Session store (when store === 'session')
        'session_key' => 'mfa_verified_at',

        // How long MFA verification remains valid (in minutes)
        // Session: null = session lifetime. Cache/Database: default 480 (8 hours).
        'lifetime' => null,

        // Cache store (when store === 'cache')
        'key_prefix' => 'mfa_verified_at',

        // Database store (when store === 'database')
        'table' => 'mfa_verifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | User Model Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how the package interacts with your User model.
    |
    */
    'user' => [
        // The model class for users (auto-detected from auth config if null)
        'model' => null,

        // The foreign key column in mfa_credentials table
        'foreign_key' => 'user_id',
    ],
];
