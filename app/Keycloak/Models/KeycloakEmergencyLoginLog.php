<?php

namespace App\Keycloak\Models;

use App\Models\Pegawai;
use Database\Factories\KeycloakEmergencyLoginLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel log emergency login Keycloak.
 * Menyimpan log akses darurat saat Keycloak tidak tersedia.
 */
class KeycloakEmergencyLoginLog extends Model
{
    /** @use HasFactory<KeycloakEmergencyLoginLogFactory> */
    use HasFactory;

    protected $table = 'keycloak_emergency_login_log';

    protected $fillable = [
        'user_id',
        'username',
        'ip_address',
        'user_agent',
        'logged_in_at',
        'logged_out_at',
        'outcome',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'logged_out_at' => 'datetime',
        ];
    }

    /**
     * Factory class untuk model ini.
     */
    protected static function newFactory(): KeycloakEmergencyLoginLogFactory
    {
        return KeycloakEmergencyLoginLogFactory::new();
    }

    /**
     * Relasi ke Pegawai yang melakukan emergency login.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'user_id');
    }
}
