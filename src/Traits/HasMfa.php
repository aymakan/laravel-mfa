<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Traits;

use Aymakan\Mfa\Models\MfaCredential;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Trait to add MFA capabilities to a User model.
 * Usage: Add `use HasMfa;` to your User model.
 */
trait HasMfa
{
    /**
     * Get all MFA credentials for this user.
     */
    public function mfaCredentials(): HasMany
    {
        return $this->hasMany(MfaCredential::class, config('mfa.user.foreign_key', 'user_id'));
    }

    /**
     * Get the active MFA credential for this user.
     */
    public function activeMfaCredential(): HasOne
    {
        return $this->hasOne(MfaCredential::class, config('mfa.user.foreign_key', 'user_id'))
            ->whereNotNull('enabled_at')
            ->latest('enabled_at');
    }

    /**
     * Check if this user has MFA enabled.
     */
    public function hasMfaEnabled(): bool
    {
        return $this->mfaCredentials()->enabled()->exists();
    }

    /**
     * Check if this user has MFA enabled for a specific type.
     */
    public function hasMfaEnabledForType(string $type): bool
    {
        return $this->mfaCredentials()->enabled()->ofType($type)->exists();
    }

    /**
     * Get the email address for MFA provisioning URI.
     * Override this method to customize the email used in authenticator apps.
     */
    public function getEmailForMfa(): string
    {
        return $this->email ?? (string) $this->getAuthIdentifier();
    }
}
