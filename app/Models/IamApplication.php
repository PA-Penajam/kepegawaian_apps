<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class IamApplication extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'nama', 'slug', 'url', 'deskripsi',
        'api_key', 'api_secret_hash', 'is_active', 'is_system',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_system' => 'boolean'];
    }

    public function roles(): HasMany
    {
        return $this->hasMany(IamRole::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(IamPermission::class);
    }

    /**
     * Generate api_key + api_secret baru. Secret hanya dikembalikan sekali.
     * Menggunakan Crypt::encryptString (BUKAN Hash::make) agar secret bisa
     * di-retrieve untuk keperluan HMAC signature verification.
     */
    public static function generateApiCredentials(): array
    {
        $key    = 'iam_' . Str::random(32);
        $secret = Str::random(64);
        $hash   = Crypt::encryptString($secret);

        return ['key' => $key, 'secret' => $secret, 'hash' => $hash];
    }

    public function verifySecret(string $secret): bool
    {
        try {
            return Crypt::decryptString($this->api_secret_hash) === $secret;
        } catch (\Exception) {
            return false;
        }
    }
}
