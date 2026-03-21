<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'pegawai_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function iamRoles(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(IamUserRole::class);
    }

    /**
     * Cek apakah user memiliki role admin di IAM.
     */
    public function isAdmin(): bool
    {
        return $this->iamRoles()->whereHas('role', fn ($q) => $q->where('slug', 'admin'))->exists();
    }

    /**
     * Cek apakah user memiliki role operator di IAM.
     */
    public function isOperator(): bool
    {
        return $this->iamRoles()->whereHas('role', fn ($q) => $q->where('slug', 'operator'))->exists();
    }

    /**
     * Cek apakah user memiliki role viewer di IAM.
     */
    public function isViewer(): bool
    {
        return $this->iamRoles()->whereHas('role', fn ($q) => $q->where('slug', 'viewer'))->exists();
    }
}
