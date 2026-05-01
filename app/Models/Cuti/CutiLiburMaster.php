<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CutiLiburMaster extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_libur_master';

    protected $fillable = [
        'tanggal',
        'keterangan',
        'is_cuti_bersama',
        'tahun',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'is_cuti_bersama' => 'boolean',
            'tahun' => 'integer',
        ];
    }
}
