<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiwayatPangkat extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'riwayat_pangkat';

    protected $fillable = [
        'pegawai_id',
        'ref_pangkat_id',
        'no_sk',
        'tanggal_sk',
        'tmt',
        'pejabat_penetap',
        'masa_kerja_tahun',
        'masa_kerja_bulan',
        'gaji_pokok',
        'is_aktif',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_sk' => 'date',
            'tmt' => 'date',
            'masa_kerja_tahun' => 'integer',
            'masa_kerja_bulan' => 'integer',
            'gaji_pokok' => 'decimal:2',
            'is_aktif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(RefPangkat::class, 'ref_pangkat_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }
}
