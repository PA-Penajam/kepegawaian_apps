<?php

namespace App\Models\NomorSurat;

use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class NomorSuratReservation extends Model
{
    use HasUlids;

    protected $table = 'nomor_surat_reservations';

    protected $fillable = [
        'nomor_urut',
        'nomor_lengkap',
        'klasifikasi',
        'tahun',
        'bulan',
        'status',
        'reserved_at',
        'confirmed_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'nomor_urut' => 'integer',
            'tahun' => 'integer',
            'bulan' => 'integer',
            'reserved_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
