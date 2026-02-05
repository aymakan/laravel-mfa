<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Verification;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\ConnectionInterface;

class DatabaseVerificationStore implements VerificationStoreInterface
{
    public function __construct(
        protected ConnectionInterface $connection,
        protected array $config = []
    ) {
        $this->config = array_merge([
            'table' => 'mfa_verifications',
            'lifetime' => 480, // minutes; default 8 hours
        ], $config);
    }

    public function markVerified(Authenticatable $user): void
    {
        $userId = $user->getAuthIdentifier();
        $verifiedAt = now();
        $minutes = $this->config['lifetime'] ?? 480;
        $expiresAt = $verifiedAt->copy()->addMinutes($minutes);

        $table = $this->config['table'];

        $this->connection->table($table)->upsert(
            [
                'user_id' => $userId,
                'verified_at' => $verifiedAt->toDateTimeString(),
                'expires_at' => $expiresAt->toDateTimeString(),
            ],
            ['user_id'],
            ['verified_at', 'expires_at']
        );
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

        $this->connection->table($this->config['table'])
            ->where('user_id', $user->getAuthIdentifier())
            ->delete();
    }

    public function getVerifiedAt(Authenticatable $user): ?\DateTimeInterface
    {
        $row = $this->connection->table($this->config['table'])
            ->where('user_id', $user->getAuthIdentifier())
            ->where('expires_at', '>', now()->toDateTimeString())
            ->first();

        if ($row === null) {
            return null;
        }

        return Carbon::parse($row->verified_at);
    }
}
