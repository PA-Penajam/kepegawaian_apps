<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BerkasChecklistTemplate extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'berkas_checklist_templates';

    protected $fillable = [
        'jenis',
        'kode',
        'nama',
        'deskripsi',
        'aktif',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
            'urutan' => 'integer',
        ];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(BerkasChecklistSubmission::class, 'berkas_checklist_template_id');
    }
}
