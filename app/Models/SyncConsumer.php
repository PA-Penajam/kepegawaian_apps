<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLogOptions;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

/**
 * Konsumen sinkronisasi data pegawai (aplikasi eksternal, mis. wfa-task).
 *
 * Setiap konsumen memiliki DUA kredensial unik (1 client 1 secret):
 * - Sanctum token (name "sync:{slug}") untuk autentikasi request ke
 *   endpoint /api/v1/pegawai/sync.
 * - HMAC secret per konsumen untuk tanda tangan X-Signature. Disimpan
 *   terenkripsi di kolom hmac_secret dan hanya ditampilkan sekali saat
 *   penerbitan/regenerasi.
 *
 * Implement Authenticatable agar konsumen dapat menjadi tokenable Sanctum
 * (guard sanctum + throttle memanggil getAuthIdentifier pada user ter-resolve).
 */
class SyncConsumer extends Model implements AuthenticatableContract
{
    use Authenticatable, HasActivityLogOptions, HasApiTokens, HasFactory, HasUlids, LogsActivity, SoftDeletes {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'sync_consumers';

    protected $fillable = [
        'nama',
        'slug',
        'base_url',
        'deskripsi',
        'is_active',
        'last_pull_at',
        'last_pull_status',
        'last_pull_rows',
        'last_connection_test_at',
        'last_connection_test_status',
        'last_connection_test_message',
    ];

    /**
     * HMAC secret tidak boleh bocor lewat serialisasi (props Inertia).
     */
    protected $hidden = [
        'hmac_secret',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_pull_at' => 'datetime',
            'last_pull_rows' => 'integer',
            'last_connection_test_at' => 'datetime',
            'hmac_secret' => 'encrypted',
        ];
    }

    public function pulls(): HasMany
    {
        return $this->hasMany(PegawaiSyncPull::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
