<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiwayatDiklat extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'riwayat_diklat';

    protected $fillable = [
        'pegawai_id',
        'ref_jenis_diklat_id',
        'nama_diklat',
        'penyelenggara',
        'tempat',
        'tanggal_mulai',
        'tanggal_selesai',
        'jam_pelajaran',
        'no_sertifikat',
        'tanggal_sertifikat',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tanggal_sertifikat' => 'date',
            'jam_pelajaran' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function jenisDiklat(): BelongsTo
    {
        return $this->belongsTo(RefJenisDiklat::class, 'ref_jenis_diklat_id');
    }
}
