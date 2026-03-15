<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Penghargaan extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'penghargaan';

    protected $fillable = [
        'pegawai_id',
        'ref_jenis_penghargaan_id',
        'nama_penghargaan',
        'no_sk',
        'tanggal_sk',
        'pejabat_penetap',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_sk' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function jenisPenghargaan(): BelongsTo
    {
        return $this->belongsTo(RefJenisPenghargaan::class, 'ref_jenis_penghargaan_id');
    }
}
