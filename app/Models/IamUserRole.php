<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HasActivityLogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class IamUserRole extends Model
{
    use HasActivityLogOptions, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }
    protected $fillable = [
        'user_id', 'iam_role_id', 'assigned_at', 'assigned_by',
    ];

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
