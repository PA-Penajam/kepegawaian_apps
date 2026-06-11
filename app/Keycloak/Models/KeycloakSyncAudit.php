<?php

namespace App\Keycloak\Models;

use App\Models\Pegawai;
use Database\Factories\KeycloakSyncAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model untuk tabel audit sinkronisasi Keycloak.
 * Menyimpan log setiap operasi sync (create, update, conflict, sync_failure).
 */
class KeycloakSyncAudit extends Model
{
    /** @use HasFactory<KeycloakSyncAuditFactory> */
    use HasFactory;

    protected $table = 'keycloak_sync_audit';

    protected $fillable = [
        'event_type',
        'pegawai_id',
        'nip',
        'conflict_type',
        'pegawai_snapshot',
        'keycloak_snapshot',
        'resolution',
        'resolved_by',
        'caused_by',
        'caused_by_nip',
    ];

    protected function casts(): array
    {
        return [
            'pegawai_snapshot' => 'array',
            'keycloak_snapshot' => 'array',
            'resolution' => 'array',
        ];
    }

    /**
     * Factory class untuk model ini.
     */
    protected static function newFactory(): KeycloakSyncAuditFactory
    {
        return KeycloakSyncAuditFactory::new();
    }

    /**
     * Relasi ke Pegawai yang di-sync.
     */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    /**
     * Relasi ke Pegawai yang memicu operasi sync.
     */
    public function causedBy(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'caused_by');
    }
}
