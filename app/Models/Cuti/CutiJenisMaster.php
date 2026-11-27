<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasCutiActivityLogOptions;
use App\Models\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CutiJenisMaster extends Model
{
    use HasCutiActivityLogOptions, HasFactory, LogsActivity {
        HasCutiActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_jenis_master';

    protected $primaryKey = 'kode';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode',
        'nama',
        'saldo_driven',
        'hak_default_per_tahun',
        'durasi_min_kalender',
        'durasi_max_kalender',
        'butuh_lampiran',
        'boleh_dicabut_setelah_disetujui',
        'penandatangan_surat',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'saldo_driven' => 'boolean',
            'butuh_lampiran' => 'boolean',
            'boleh_dicabut_setelah_disetujui' => 'boolean',
            'aktif' => 'boolean',
        ];
    }
}
