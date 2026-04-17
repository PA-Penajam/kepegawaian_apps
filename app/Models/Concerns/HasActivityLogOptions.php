<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Support\LogOptions;

trait HasActivityLogOptions
{
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logFillable()
            ->setDescriptionForEvent(fn (string $eventName) => $eventName);
    }
}
