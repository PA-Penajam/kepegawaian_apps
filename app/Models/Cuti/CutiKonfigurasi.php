<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CutiKonfigurasi extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_konfigurasi';

    protected $fillable = [
        'key',
        'value',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
