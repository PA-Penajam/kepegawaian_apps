<?php

namespace App\Models;

use Database\Factories\RefStatusPegawaiFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefStatusPegawai extends Model
{
    /** @use HasFactory<RefStatusPegawaiFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_status_pegawai';

    protected $fillable = [
        'kode',
        'nama',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
}
