<?php

namespace App\Models\BerkasChecklist;

use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class BerkasChecklistSubmissionItem extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    public const STATUS_BELUM_ADA = 'belum_ada';

    public const STATUS_ADA = 'ada';

    public const STATUS_VALID = 'valid';

    public const STATUS_TIDAK_VALID = 'tidak_valid';

    public const STATUSES = [
        self::STATUS_BELUM_ADA,
        self::STATUS_ADA,
        self::STATUS_VALID,
        self::STATUS_TIDAK_VALID,
    ];

    protected $fillable = [
        'berkas_checklist_submission_id',
        'berkas_checklist_item_id',
        'status',
        'catatan',
        'file_path',
        'file_original_name',
        'file_mime',
        'file_size',
        'validated_by',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'validated_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('berkas_checklist_submission_item');
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(BerkasChecklistSubmission::class, 'berkas_checklist_submission_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(BerkasChecklistItem::class, 'berkas_checklist_item_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'validated_by');
    }
}
