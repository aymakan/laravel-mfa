<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Contracts;

use Aymakan\Mfa\Models\MfaCredential;
use Illuminate\Contracts\Auth\Authenticatable;

interface MfaDriverInterface
{
    /**
     * Generate a new secret for MFA enrollment.
     */
    public function generateSecret(): string;

    /**
     * Generate the provisioning URI for QR code generation.
     *
     * @param Authenticatable $user The user enrolling in MFA
     * @param string $secret The generated secret
     * @return string The otpauth:// URI for QR code
     */
    public function getProvisioningUri(Authenticatable $user, string $secret): string;

    /**
     * Verify a one-time password against a credential.
     *
     * @param MfaCredential $credential The user's MFA credential
     * @param string $otp The one-time password to verify
     * @return bool True if the OTP is valid
     */
    public function verify(MfaCredential $credential, string $otp): bool;

    /**
     * Verify a one-time password against a secret (for enrollment verification).
     *
     * @param string $secret The secret to verify against
     * @param string $otp The one-time password to verify
     * @return bool True if the OTP is valid
     */
    public function verifySecret(string $secret, string $otp): bool;

    /**
     * Get the driver type identifier.
     */
    public function getType(): string;
}
