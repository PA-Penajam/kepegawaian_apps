<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IamSsoCode extends Model
{
    use Prunable;

    public const UPDATED_AT = null; // tabel hanya punya created_at

    protected $fillable = [
        'code', 'user_id', 'app_slug', 'used_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isExpired() && ! $this->isUsed();
    }

    // Prune records yang expired lebih dari 24 jam yang lalu
    public function prunable(): Builder
    {
        return static::where('expires_at', '<', now()->subDay());
    }
}
