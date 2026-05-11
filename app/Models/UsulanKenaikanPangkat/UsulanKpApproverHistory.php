<?php

namespace App\Models\UsulanKenaikanPangkat;

use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class UsulanKpApproverHistory extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    public const UPDATED_AT = null;

    protected $table = 'usulan_kp_approver_history';

    protected $fillable = [
        'usulan_kenaikan_pangkat_id',
        'step_urutan',
        'user_id',
        'action',
        'catatan',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'step_urutan' => 'integer',
            'meta' => 'array',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'user_id');
    }
}
