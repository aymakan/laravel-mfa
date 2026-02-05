<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Verification;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

class CacheVerificationStore implements VerificationStoreInterface
{
    public function __construct(
        protected CacheRepository $cache,
        protected array $config = []
    ) {
        $this->config = array_merge([
            'key_prefix' => 'mfa_verified_at',
            'lifetime' => 480, // minutes; default 8 hours
        ], $config);
    }

    public function markVerified(Authenticatable $user): void
    {
        $key = $this->getCacheKey($user);
        $minutes = $this->config['lifetime'] ?? 480;
        $this->cache->put($key, now()->toIso8601String(), $minutes * 60);
    }

    public function isVerified(Authenticatable $user): bool
    {
        return $this->getVerifiedAt($user) !== null;
    }

    public function clear(?Authenticatable $user): void
    {
        if ($user === null) {
            return;
        }

        $this->cache->forget($this->getCacheKey($user));
    }

    public function getVerifiedAt(Authenticatable $user): ?\DateTimeInterface
    {
        $key = $this->getCacheKey($user);
        $value = $this->cache->get($key);

        if ($value === null) {
            return null;
        }

        return Carbon::parse($value);
    }

    protected function getCacheKey(Authenticatable $user): string
    {
        $prefix = $this->config['key_prefix'];
        $userId = $user->getAuthIdentifier();

        return "{$prefix}_{$userId}";
    }
}
