<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IamUserRole extends Model
{
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
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
