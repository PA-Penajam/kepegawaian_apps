<?php

namespace App\Models;

use Database\Factories\RefStatusKepegawaianFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefStatusKepegawaian extends Model
{
    /** @use HasFactory<RefStatusKepegawaianFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_status_kepegawaian';

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
