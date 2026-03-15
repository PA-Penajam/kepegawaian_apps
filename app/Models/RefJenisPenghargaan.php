<?php

namespace App\Models;

use Database\Factories\RefJenisPenghargaanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefJenisPenghargaan extends Model
{
    /** @use HasFactory<RefJenisPenghargaanFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_jenis_penghargaan';

    protected $fillable = [
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
