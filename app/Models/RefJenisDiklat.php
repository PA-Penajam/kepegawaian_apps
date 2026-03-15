<?php

namespace App\Models;

use Database\Factories\RefJenisDiklatFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefJenisDiklat extends Model
{
    /** @use HasFactory<RefJenisDiklatFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_jenis_diklat';

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
