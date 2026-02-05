<?php

declare(strict_types=1);

namespace Aymakan\Mfa\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfaCredential extends Model
{
    protected $table = 'mfa_credentials';

    protected $fillable = [
        'user_id',
        'type',
        'secret',
        'enabled_at',
        'last_used_at',
        'last_used_otp',
    ];

    protected $hidden = [
        'secret',
        'last_used_otp',
    ];

    protected $casts = [
        'enabled_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user that owns this MFA credential.
     */
    public function user(): BelongsTo
    {
        $userModel = config('mfa.user.model') ?? config('auth.providers.users.model');
        $foreignKey = config('mfa.user.foreign_key', 'user_id');

        return $this->belongsTo($userModel, $foreignKey);
    }

    /**
     * Check if this credential is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled_at !== null;
    }

    /**
     * Enable this credential.
     */
    public function enable(): bool
    {
        return $this->update(['enabled_at' => now()]);
    }

    /**
     * Disable this credential.
     */
    public function disable(): bool
    {
        return $this->update([
            'enabled_at' => null,
            'last_used_at' => null,
            'last_used_otp' => null,
        ]);
    }

    /**
     * Mark this credential as used with the given OTP.
     */
    public function markAsUsed(string $otp): bool
    {
        return $this->update([
            'last_used_at' => now(),
            'last_used_otp' => $otp,
        ]);
    }

    /**
     * Check if the given OTP was the last used OTP (replay protection).
     */
    public function wasOtpUsed(string $otp): bool
    {
        return $this->last_used_otp === $otp;
    }

    /**
     * Scope to get enabled credentials only.
     */
    public function scopeEnabled($query)
    {
        return $query->whereNotNull('enabled_at');
    }

    /**
     * Scope to filter by credential type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
