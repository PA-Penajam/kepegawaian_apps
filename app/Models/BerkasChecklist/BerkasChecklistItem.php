<?php

namespace App\Models\BerkasChecklist;

use App\Models\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class BerkasChecklistItem extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $fillable = [
        'berkas_checklist_template_id',
        'kode',
        'nama',
        'deskripsi',
        'wajib',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
            'urutan' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('berkas_checklist_item');
    }

    public function scopeWajib(Builder $query): Builder
    {
        return $query->where('wajib', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('urutan')->orderBy('kode');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BerkasChecklistTemplate::class, 'berkas_checklist_template_id');
    }
}
