<?php

namespace App\Models\BerkasChecklist;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class BerkasChecklistSubmission extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $fillable = [
        'berkas_checklist_template_id',
        'subject_type',
        'subject_id',
        'pegawai_id',
        'status_kelengkapan',
        'persentase',
    ];

    protected function casts(): array
    {
        return [
            'persentase' => 'integer',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BerkasChecklistTemplate::class, 'berkas_checklist_template_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BerkasChecklistSubmissionItem::class, 'berkas_checklist_submission_id');
    }
}
