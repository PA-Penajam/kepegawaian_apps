<?php

namespace App\Models\UsulanKenaikanPangkat;

use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class UsulanKpStateHistory extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    public const UPDATED_AT = null;

    protected $table = 'usulan_kp_state_history';

    protected $fillable = [
        'usulan_kenaikan_pangkat_id',
        'from_state',
        'to_state',
        'transitioned_by',
        'catatan',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }

    public function usulan(): BelongsTo
    {
        return $this->belongsTo(UsulanKenaikanPangkat::class, 'usulan_kenaikan_pangkat_id');
    }

    public function transitionedBy(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'transitioned_by');
    }
}
