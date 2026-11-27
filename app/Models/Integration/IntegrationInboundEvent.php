<?php

namespace App\Models\Integration;

use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class IntegrationInboundEvent extends Model
{
    use HasUlids;

    protected $table = 'integration_inbound_events';

    protected $fillable = [
        'source',
        'event_id',
        'event_type',
        'payload',
        'occurred_at',
        'processed_at',
        'failed_reason',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
