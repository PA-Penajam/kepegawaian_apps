<?php

namespace App\Models\NomorSurat;

use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class NomorSuratSequence extends Model
{
    use HasUlids;

    protected $table = 'nomor_surat_sequences';

    protected $fillable = [
        'klasifikasi',
        'tahun',
        'next_number',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'next_number' => 'integer',
        ];
    }
}
