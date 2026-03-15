<?php

namespace App\Models;

use Database\Factories\RefPangkatFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefPangkat extends Model
{
    /** @use HasFactory<RefPangkatFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_pangkat';

    protected $fillable = [
        'kode',
        'nama',
        'golongan',
        'ruang',
        'tingkat',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }
}
