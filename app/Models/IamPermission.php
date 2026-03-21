<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IamPermission extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'iam_application_id', 'nama', 'slug', 'group', 'keterangan',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(IamApplication::class, 'iam_application_id');
    }
}
