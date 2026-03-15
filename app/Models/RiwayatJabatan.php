<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RiwayatJabatan extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'riwayat_jabatan';

    protected $fillable = [
        'pegawai_id',
        'ref_jabatan_id',
        'ref_unit_kerja_id',
        'no_sk',
        'tanggal_sk',
        'tmt',
        'pejabat_penetap',
        'is_aktif',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_sk' => 'date',
            'tmt' => 'date',
            'is_aktif' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(RefJabatan::class, 'ref_jabatan_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(RefUnitKerja::class, 'ref_unit_kerja_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('is_aktif', true);
    }
}
