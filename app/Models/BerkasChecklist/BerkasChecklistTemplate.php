<?php

namespace App\Models\BerkasChecklist;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class BerkasChecklistTemplate extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity, SoftDeletes {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

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
            'deleted_at' => 'datetime',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeByDomain(Builder $query, string $domain): Builder
    {
        return $query->where('jenis', $domain);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BerkasChecklistItem::class, 'berkas_checklist_template_id');
    }
}
