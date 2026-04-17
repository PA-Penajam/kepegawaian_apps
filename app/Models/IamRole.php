<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\HasActivityLogOptions;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class IamRole extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity, SoftDeletes {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $fillable = [
        'iam_application_id', 'nama', 'slug', 'keterangan', 'is_system',
    ];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(IamApplication::class, 'iam_application_id');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            IamPermission::class,
            'iam_role_permissions',
            'iam_role_id',
            'iam_permission_id'
        )->withTimestamps();
    }
}
