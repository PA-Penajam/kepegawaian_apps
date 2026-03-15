<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model as EloquentModel;

abstract class Model extends EloquentModel
{
    /**
     * Menyiapkan tanggal untuk serialisasi array/JSON dengan format Y-m-d.
     */
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }
}
