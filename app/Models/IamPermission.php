<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLogOptions;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class IamPermission extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity, SoftDeletes {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $fillable = [
        'iam_application_id', 'nama', 'slug', 'group', 'keterangan',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(IamApplication::class, 'iam_application_id');
    }
}
