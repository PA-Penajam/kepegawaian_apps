<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CutiJenisPerStatusPegawai extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_jenis_per_status_pegawai';

    protected $fillable = [
        'jenis_cuti_kode',
        'status_kepegawaian',
        'boleh',
        'hak_per_tahun',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'boleh' => 'boolean',
            'hak_per_tahun' => 'integer',
        ];
    }

    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(CutiJenisMaster::class, 'jenis_cuti_kode', 'kode');
    }
}
