<?php

namespace App\Models;

use Database\Factories\RefUnitKerjaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefUnitKerja extends Model
{
    /** @use HasFactory<RefUnitKerjaFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_unit_kerja';

    protected $fillable = [
        'kode',
        'nama',
        'parent_id',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('urutan');
    }

    public function pegawai(): HasMany
    {
        return $this->hasMany(Pegawai::class, 'ref_unit_kerja_id');
    }
}
