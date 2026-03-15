<?php

namespace App\Models;

use App\Enums\HubunganKeluarga;
use App\Enums\JenisKelamin;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Keluarga extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'keluarga';

    protected $fillable = [
        'pegawai_id',
        'hubungan',
        'nama',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'pekerjaan',
        'pendidikan',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'hubungan' => HubunganKeluarga::class,
            'jenis_kelamin' => JenisKelamin::class,
            'tanggal_lahir' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }
}
