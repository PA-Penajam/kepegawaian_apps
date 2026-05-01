<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CutiAlokasiTahunan extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_alokasi_tahunan';

    protected $fillable = [
        'pegawai_nip',
        'jenis_cuti_kode',
        'tahun_hak',
        'hak_awal',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tahun_hak' => 'integer',
            'hak_awal' => 'integer',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_nip', 'nip');
    }

    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(CutiJenisMaster::class, 'jenis_cuti_kode', 'kode');
    }
}
