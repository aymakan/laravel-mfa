<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Verification;

use Illuminate\Contracts\Auth\Authenticatable;

interface VerificationStoreInterface
{
    /**
     * Mark MFA as verified for the given user.
     */
    public function markVerified(Authenticatable $user): void;

    /**
     * Check if MFA is verified for the given user.
     */
    public function isVerified(Authenticatable $user): bool;

    /**
     * Clear MFA verification state for the given user.
     */
    public function clear(?Authenticatable $user): void;

    /**
     * Get the timestamp when MFA was verified, or null if not verified.
     */
    public function getVerifiedAt(Authenticatable $user): ?\DateTimeInterface;
}
