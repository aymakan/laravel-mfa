<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Verification;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;

class SessionVerificationStore implements VerificationStoreInterface
{
    public function __construct(
        protected Session $session,
        protected array $config = []
    ) {
        $this->config = array_merge([
            'session_key' => 'mfa_verified_at',
            'lifetime' => null,
        ], $config);
    }

    public function markVerified(Authenticatable $user): void
    {
        $key = $this->getSessionKey($user);
        $this->session->put($key, now()->toIso8601String());
    }

    public function isVerified(Authenticatable $user): bool
    {
        $verifiedAt = $this->getVerifiedAt($user);

        if ($verifiedAt === null) {
            return false;
        }

        // Check if verification has expired
        $lifetime = $this->config['lifetime'];

        if ($lifetime !== null) {
            $expiresAt = Carbon::parse($verifiedAt)->addMinutes($lifetime);

            if (now()->isAfter($expiresAt)) {
                $this->clear($user);

                return false;
            }
        }

        return true;
    }

    public function clear(?Authenticatable $user): void
    {
        if ($user === null) {
            return;
        }

        $key = $this->getSessionKey($user);
        $this->session->forget($key);
    }

    public function getVerifiedAt(Authenticatable $user): ?\DateTimeInterface
    {
        $key = $this->getSessionKey($user);
        $value = $this->session->get($key);

        if ($value === null) {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * Generate a unique session key for the user.
     * This prevents session fixation issues when switching users.
     */
    protected function getSessionKey(Authenticatable $user): string
    {
        $baseKey = $this->config['session_key'];
        $userId = $user->getAuthIdentifier();

        return "{$baseKey}_{$userId}";
    }
}
