<?php

namespace App\Models;

use Database\Factories\RefJenisHukumanDisiplinFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefJenisHukumanDisiplin extends Model
{
    /** @use HasFactory<RefJenisHukumanDisiplinFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_jenis_hukuman_disiplin';

    protected $fillable = [
        'nama',
        'tingkat',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
}
