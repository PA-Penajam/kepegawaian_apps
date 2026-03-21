<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IamRolePermission extends Model
{
    protected $fillable = [
        'iam_role_id', 'iam_permission_id',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(IamRole::class, 'iam_role_id');
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(IamPermission::class, 'iam_permission_id');
    }
}
