<?php

namespace App\Models\Cuti;

use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CutiPerizinanEksternal extends Model
{
    use HasUlids;

    protected $table = 'cuti_perizinan_eksternal';

    protected $fillable = [
        'pengajuan_id',
        'pemberi_izin',
        'instansi_nama',
        'nomor_registrasi_eksternal',
        'tanggal_pengajuan_eksternal',
        'catatan_pengajuan',
        'sisukma_incoming_mail_public_id',
        'sisukma_agenda_number',
        'nomor_sk',
        'tanggal_sk',
        'keputusan',
        'catatan_keputusan',
        'recorded_by_nip',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_pengajuan_eksternal' => 'date',
            'tanggal_sk' => 'date',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Pengajuan cuti yang sedang memproses perizinan di instansi atasan.
     *
     * @return BelongsTo<CutiPengajuan, $this>
     */
    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(CutiPengajuan::class, 'pengajuan_id');
    }
}
