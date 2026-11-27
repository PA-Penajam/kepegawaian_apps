<?php

namespace App\Models\SuratDinas;

use App\Enums\StatusSuratDinas;
use App\Models\Model;
use Database\Factories\SuratDinas\SuratDinasRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SuratDinasRequest extends Model
{
    /** @use HasFactory<SuratDinasRequestFactory> */
    use HasFactory, HasUlids;

    protected $table = 'surat_dinas_requests';

    protected $fillable = [
        'suratable_type',
        'suratable_id',
        'jenis',
        'klasifikasi',
        'penandatangan',
        'document_type',
        'perihal',
        'tujuan_nama',
        'tujuan_instansi',
        'tujuan_alamat',
        'external_path',
        'status',
        'sisukma_public_id',
        'nomor_agenda',
        'nomor_surat',
        'catatan_revisi',
        'alasan_void',
        'final_checksum',
        'final_size_bytes',
        'final_uploaded_at',
        'requested_by_nip',
        'attempts',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusSuratDinas::class,
            'final_size_bytes' => 'integer',
            'final_uploaded_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    protected static function newFactory(): SuratDinasRequestFactory
    {
        return SuratDinasRequestFactory::new();
    }

    /**
     * Entitas sumber yang memicu pembuatan surat dinas (CutiPengajuan atau UsulanKenaikanPangkat).
     */
    public function suratable(): MorphTo
    {
        return $this->morphTo();
    }
}
