<?php

namespace App\Models;

use App\Enums\JenjangPendidikan;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiwayatPendidikan extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'riwayat_pendidikan';

    protected $fillable = [
        'pegawai_id',
        'jenjang',
        'nama_sekolah',
        'jurusan',
        'tahun_lulus',
        'no_ijazah',
        'tanggal_ijazah',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jenjang' => JenjangPendidikan::class,
            'tanggal_ijazah' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }
}
