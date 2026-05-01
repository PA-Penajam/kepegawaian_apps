<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CutiEventDelivery extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_event_deliveries';

    protected $fillable = [
        'event_id',
        'consumer_id',
        'status',
        'attempts',
        'last_attempt_at',
        'delivered_at',
        'last_error',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
            'next_retry_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(CutiEvent::class, 'event_id');
    }
}
