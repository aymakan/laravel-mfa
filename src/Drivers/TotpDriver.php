<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Drivers;

use Aymakan\Mfa\Contracts\MfaDriverInterface;
use Aymakan\Mfa\Models\MfaCredential;
use Illuminate\Contracts\Auth\Authenticatable;
use PragmaRX\Google2FA\Google2FA;

class TotpDriver implements MfaDriverInterface
{
    protected Google2FA $google2fa;

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'issuer' => 'Laravel',
            'digits' => 6,
            'period' => 30,
            'algorithm' => 'sha1',
            'window' => 1,
        ], $config);

        $this->google2fa = new Google2FA();
        $this->google2fa->setWindow($this->config['window']);
    }

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    public function getProvisioningUri(Authenticatable $user, string $secret): string
    {
        $email = $this->getUserEmail($user);
        $issuer = $this->config['issuer'];

        return $this->google2fa->getQRCodeUrl(
            $issuer,
            $email,
            $secret
        );
    }

    public function verify(MfaCredential $credential, string $otp): bool
    {
        return $this->verifySecret($credential->secret, $otp);
    }

    public function verifySecret(string $secret, string $otp): bool
    {
        // Normalize OTP (remove spaces, ensure string)
        $otp = preg_replace('/\s+/', '', $otp);

        if (!$this->isValidOtpFormat($otp)) {
            return false;
        }

        return $this->google2fa->verifyKey($secret, $otp);
    }

    public function getType(): string
    {
        return 'totp';
    }

    /**
     * Get the user's email for the provisioning URI.
     */
    protected function getUserEmail(Authenticatable $user): string
    {
        if (isset($user->email)) {
            return $user->email;
        }

        // Fallback to auth identifier
        return (string) $user->getAuthIdentifier();
    }

    /**
     * Validate OTP format before verification.
     */
    protected function isValidOtpFormat(string $otp): bool
    {
        $expectedDigits = $this->config['digits'];

        return preg_match('/^\d{' . $expectedDigits . '}$/', $otp) === 1;
    }

    /**
     * Get the current TOTP timestamp for debugging.
     */
    public function getCurrentTimestamp(): int
    {
        return $this->google2fa->getTimestamp();
    }
}
