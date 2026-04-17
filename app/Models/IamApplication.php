<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class IamApplication extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'nama', 'slug', 'url', 'deskripsi', 'is_active',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logFillable()
            ->setDescriptionForEvent(fn (string $eventName) => $eventName);
    }

    /**
     * Field sensitif yang tidak boleh muncul di JSON/response.
     * api_secret_hash berisi secret terenkripsi yang tidak boleh diekspos.
     */
    protected $hidden = [
        'api_secret_hash',
    ];

    /**
     * Boot model untuk auto-generate api credentials saat creating.
     * Ini diperlukan karena api_key dan api_secret_hash tidak mass-assignable,
     * tapi kolom tersebut NOT NULL di database.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (IamApplication $app) {
            // Auto-generate credentials jika belum diset
            if (empty($app->api_key)) {
                $secret = Str::random(64);
                $app->api_key = 'iam_'.Str::random(32);
                $app->api_secret_hash = Crypt::encryptString($secret);
            }

            // Default is_system = false jika belum set
            if (!isset($app->is_system)) {
                $app->is_system = false;
            }
        });
    }

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
        $key = 'iam_'.Str::random(32);
        $secret = Str::random(64);
        $hash = Crypt::encryptString($secret);

        return ['key' => $key, 'secret' => $secret, 'hash' => $hash];
    }

    /**
     * Verifikasi api_secret plain-text dengan stored hash.
     * Menggunakan hash_equals untuk perbandingan constant-time (anti timing attack).
     */
    public function verifySecret(string $secret): bool
    {
        try {
            $storedSecret = Crypt::decryptString($this->api_secret_hash);
            // hash_equals memastikan perbandingan constant-time (anti timing attack)
            return hash_equals($storedSecret, $secret);
        } catch (\Exception) {
            return false;
        }
    }
}
