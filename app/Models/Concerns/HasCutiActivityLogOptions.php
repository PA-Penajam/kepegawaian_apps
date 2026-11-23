<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Support\LogOptions;

/**
 * Konfigurasi activity log khusus modul Cuti agar tercatat pada log_name 'cuti'.
 */
trait HasCutiActivityLogOptions
{
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('cuti')
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logFillable()
            ->setDescriptionForEvent(fn (string $eventName): string => $eventName);
    }
}
