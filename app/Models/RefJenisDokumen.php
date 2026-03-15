<?php

namespace App\Models;

use Database\Factories\RefJenisDokumenFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefJenisDokumen extends Model
{
    /** @use HasFactory<RefJenisDokumenFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_jenis_dokumen';

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
