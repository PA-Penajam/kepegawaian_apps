<?php

namespace App\Models;

use App\Enums\JenisJabatan;
use Database\Factories\RefJabatanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefJabatan extends Model
{
    /** @use HasFactory<RefJabatanFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_jabatan';

    protected $fillable = [
        'kode',
        'nama',
        'jenis_jabatan',
        'eselon',
        'kelas_jabatan',
    ];

    protected function casts(): array
    {
        return [
            'jenis_jabatan' => JenisJabatan::class,
            'kelas_jabatan' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }
}
