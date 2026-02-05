<?php

declare(strict_types=1);

namespace Aymakan\Mfa;

use Aymakan\Mfa\Contracts\MfaDriverInterface;
use Aymakan\Mfa\Drivers\TotpDriver;
use Aymakan\Mfa\Models\MfaCredential;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

class MfaManager
{
    protected array $drivers = [];

    public function __construct(protected array $config)
    {
    }

    /**
     * Get the default driver instance.
     */
    public function driver(?string $name = null): MfaDriverInterface
    {
        $name = $name ?? $this->getDefaultDriver();

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'totp';
    }

    /**
     * Create a driver instance by name.
     */
    protected function createDriver(string $name): MfaDriverInterface
    {
        $method = 'create' . ucfirst($name) . 'Driver';

        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        throw new InvalidArgumentException("MFA driver [{$name}] is not supported.");
    }

    /**
     * Create the TOTP driver instance.
     */
    protected function createTotpDriver(): TotpDriver
    {
        $driverConfig = $this->config['drivers']['totp'] ?? [];

        return new TotpDriver($driverConfig);
    }

    /**
     * Check if MFA is enabled globally.
     */
    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * Check if this user must complete MFA (enroll if not enrolled, then verify).
     * True when enforcement is 'required' globally, or when the user has the
     * per-user attribute set (e.g. mfa_required = true).
     */
    public function userMustCompleteMfa(Authenticatable $user): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        $enforcement = $this->config['enforcement'] ?? 'optional';
        if ($enforcement === 'required') {
            return true;
        }

        $attribute = $this->config['enforcement_per_user_attribute'] ?? null;
        if ($attribute !== null && $attribute !== '') {
            return (bool) ($user->{$attribute} ?? false);
        }

        return false;
    }

    /**
     * Check if a user has MFA enabled.
     */
    public function userHasMfaEnabled(Authenticatable $user): bool
    {
        return $this->getEnabledCredential($user) !== null;
    }

    /**
     * Get the enabled MFA credential for a user.
     */
    public function getEnabledCredential(Authenticatable $user, ?string $type = null): ?MfaCredential
    {
        $type = $type ?? $this->getDefaultDriver();

        return MfaCredential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('type', $type)
            ->enabled()
            ->first();
    }

    /**
     * Get all MFA credentials for a user.
     */
    public function getCredentials(Authenticatable $user): \Illuminate\Database\Eloquent\Collection
    {
        return MfaCredential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->get();
    }

    /**
     * Get or create a credential for enrollment (not enabled yet).
     */
    public function getOrCreatePendingCredential(Authenticatable $user, ?string $type = null): MfaCredential
    {
        $type = $type ?? $this->getDefaultDriver();

        $credential = MfaCredential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('type', $type)
            ->whereNull('enabled_at')
            ->first();

        if ($credential) {
            return $credential;
        }

        $driver = $this->driver($type);

        return MfaCredential::create([
            'user_id' => $user->getAuthIdentifier(),
            'type' => $type,
            'secret' => $driver->generateSecret(),
            'enabled_at' => null,
        ]);
    }

    /**
     * Verify an OTP and enable the credential if not already enabled.
     * Returns true if verification succeeded.
     */
    public function verifyAndEnable(Authenticatable $user, string $otp, ?string $type = null): bool
    {
        $type = $type ?? $this->getDefaultDriver();
        $driver = $this->driver($type);

        // First check if user has an enabled credential
        $credential = $this->getEnabledCredential($user, $type);

        if ($credential) {
            // Replay protection
            if ($credential->wasOtpUsed($otp)) {
                return false;
            }

            if ($driver->verify($credential, $otp)) {
                $credential->markAsUsed($otp);

                return true;
            }

            return false;
        }

        // Check for pending credential (enrollment flow)
        $pendingCredential = MfaCredential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('type', $type)
            ->whereNull('enabled_at')
            ->first();

        if ($pendingCredential && $driver->verify($pendingCredential, $otp)) {
            $pendingCredential->enable();
            $pendingCredential->markAsUsed($otp);

            return true;
        }

        return false;
    }

    /**
     * Verify an OTP for an already-enabled credential.
     * Does not enable pending credentials.
     */
    public function verify(Authenticatable $user, string $otp, ?string $type = null): bool
    {
        $type = $type ?? $this->getDefaultDriver();
        $credential = $this->getEnabledCredential($user, $type);

        if (!$credential) {
            return false;
        }

        // Replay protection
        if ($credential->wasOtpUsed($otp)) {
            return false;
        }

        $driver = $this->driver($type);

        if ($driver->verify($credential, $otp)) {
            $credential->markAsUsed($otp);

            return true;
        }

        return false;
    }

    /**
     * Disable MFA for a user.
     */
    public function disable(Authenticatable $user, ?string $type = null): bool
    {
        $type = $type ?? $this->getDefaultDriver();

        $credential = MfaCredential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('type', $type)
            ->first();

        if ($credential) {
            return $credential->delete();
        }

        return false;
    }

    /**
     * Generate the provisioning URI for QR code generation.
     */
    public function getProvisioningUri(Authenticatable $user, ?string $type = null): ?string
    {
        $type = $type ?? $this->getDefaultDriver();

        $credential = MfaCredential::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->where('type', $type)
            ->first();

        if (!$credential) {
            return null;
        }

        $driver = $this->driver($type);

        return $driver->getProvisioningUri($user, $credential->secret);
    }

    /**
     * Get error configuration.
     */
    public function getErrorConfig(string $key): array
    {
        return $this->config['errors'][$key] ?? [
            'status' => 500,
            'code' => 'MFA_ERROR',
            'message' => 'An MFA error occurred.',
        ];
    }

    /**
     * Get rate limit configuration.
     */
    public function getRateLimitConfig(): array
    {
        return $this->config['rate_limit'] ?? [
            'max_attempts' => 5,
            'decay_minutes' => 5,
        ];
    }
}
