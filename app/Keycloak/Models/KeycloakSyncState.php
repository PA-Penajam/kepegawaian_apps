<?php

namespace App\Keycloak\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model untuk tabel state sinkronisasi Keycloak.
 * Menyimpan informasi sync terakhir (waktu, tipe, jumlah, konflik).
 */
class KeycloakSyncState extends Model
{
    protected $table = 'keycloak_sync_state';

    protected $fillable = [
        'last_sync_at',
        'last_sync_type',
        'total_synced',
        'total_conflicts',
        'sync_metadata',
    ];

    protected function casts(): array
    {
        return [
            'sync_metadata' => 'array',
        ];
    }
}
