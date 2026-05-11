<?php

namespace App\Models\UsulanKenaikanPangkat;

use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class UsulanKpApprovalStep extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'usulan_kp_approval_steps';

    protected $fillable = [
        'usulan_kenaikan_pangkat_id',
        'urutan',
        'role_required',
        'approver_user_id',
        'status',
        'catatan',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(UsulanKenaikanPangkat::class, 'usulan_kenaikan_pangkat_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'approver_user_id');
    }
}
