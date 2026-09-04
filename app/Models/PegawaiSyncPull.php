<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak satu tarikan (pull) data pegawai oleh konsumen tertentu.
 *
 * Dicatat setiap kali endpoint /api/v1/pegawai/sync dilayani, sehingga
 * halaman Klien Sinkronisasi dapat menampilkan kesehatan & riwayat
 * sinkronisasi per konsumen tanpa mengubah kontrak API.
 */
class PegawaiSyncPull extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'pegawai_sync_pulls';

    protected $fillable = [
        'sync_consumer_id',
        'status',
        'rows_returned',
        'page',
        'per_page',
        'duration_ms',
        'token_name',
        'client_agent',
        'pulled_at',
    ];

    protected function casts(): array
    {
        return [
            'rows_returned' => 'integer',
            'page' => 'integer',
            'per_page' => 'integer',
            'duration_ms' => 'integer',
            'pulled_at' => 'datetime',
        ];
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(SyncConsumer::class, 'sync_consumer_id');
    }
}
