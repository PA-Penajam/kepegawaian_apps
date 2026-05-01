<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CutiPengajuanApproverHistory extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_pengajuan_approver_history';

    const UPDATED_AT = null;

    protected $fillable = [
        'pengajuan_id',
        'role',
        'from_pegawai_nip',
        'to_pegawai_nip',
        'alasan',
        'aktor_pegawai_nip',
    ];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(CutiPengajuan::class, 'pengajuan_id');
    }

    public function fromPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'from_pegawai_nip', 'nip');
    }

    public function toPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'to_pegawai_nip', 'nip');
    }

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'aktor_pegawai_nip', 'nip');
    }
}
