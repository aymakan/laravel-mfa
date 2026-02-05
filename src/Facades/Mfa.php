<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Facades;

use Aymakan\Mfa\MfaManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Aymakan\Mfa\Contracts\MfaDriverInterface driver(?string $name = null)
 * @method static string getDefaultDriver()
 * @method static bool isEnabled()
 * @method static bool userHasMfaEnabled(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static \Aymakan\Mfa\Models\MfaCredential|null getEnabledCredential(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $type = null)
 * @method static \Illuminate\Database\Eloquent\Collection getCredentials(\Illuminate\Contracts\Auth\Authenticatable $user)
 * @method static \Aymakan\Mfa\Models\MfaCredential getOrCreatePendingCredential(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $type = null)
 * @method static bool verifyAndEnable(\Illuminate\Contracts\Auth\Authenticatable $user, string $otp, ?string $type = null)
 * @method static bool verify(\Illuminate\Contracts\Auth\Authenticatable $user, string $otp, ?string $type = null)
 * @method static bool disable(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $type = null)
 * @method static string|null getProvisioningUri(\Illuminate\Contracts\Auth\Authenticatable $user, ?string $type = null)
 * @method static array getErrorConfig(string $key)
 * @method static array getRateLimitConfig()
 *
 * @see \Aymakan\Mfa\MfaManager
 */
class Mfa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MfaManager::class;
    }
}
