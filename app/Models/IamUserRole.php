<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class IamUserRole extends Model
{
    use LogsActivity;
    protected $fillable = [
        'user_id', 'iam_role_id', 'assigned_at', 'assigned_by',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logFillable()
            ->setDescriptionForEvent(fn (string $eventName) => $eventName);
    }

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime'];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(IamRole::class, 'iam_role_id');
    }

    public function assignedByUser(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'assigned_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'user_id');
    }
}
