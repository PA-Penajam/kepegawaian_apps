<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CutiPengajuanApprovalStep extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_pengajuan_approval_steps';

    protected $fillable = [
        'pengajuan_id',
        'role',
        'action',
        'aktor_pegawai_nip',
        'catatan',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(CutiPengajuan::class, 'pengajuan_id');
    }

    public function aktor(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'aktor_pegawai_nip', 'nip');
    }
}
