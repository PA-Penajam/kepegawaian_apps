<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CutiSaldoLedger extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_saldo_ledger';

    const UPDATED_AT = null;

    protected $fillable = [
        'pegawai_nip',
        'jenis_cuti_kode',
        'tahun_hak',
        'jenis_transaksi',
        'jumlah_hari',
        'pengajuan_id',
        'keterangan',
        'aktor_pegawai_nip',
    ];

    protected function casts(): array
    {
        return [
            'tahun_hak' => 'integer',
            'jumlah_hari' => 'integer',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_nip', 'nip');
    }

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'aktor_pegawai_nip', 'nip');
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(CutiPengajuan::class, 'pengajuan_id');
    }

    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(CutiJenisMaster::class, 'jenis_cuti_kode', 'kode');
    }
}
