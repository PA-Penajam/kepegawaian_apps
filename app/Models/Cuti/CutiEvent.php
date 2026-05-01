<?php

namespace App\Models\Cuti;

use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CutiEvent extends Model
{
    use HasUuids;

    protected $table = 'cuti_events';

    const UPDATED_AT = null;

    protected $fillable = [
        'aggregate_type',
        'aggregate_id',
        'event_type',
        'payload',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CutiEventDelivery::class, 'event_id');
    }
}
