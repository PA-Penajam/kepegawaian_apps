<?php

namespace App\Models\UsulanKenaikanPangkat;

use App\Models\BerkasChecklistSubmission;
use App\Models\Model;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\States\UsulanKenaikanPangkat\UsulanKenaikanPangkatState;
use Database\Factories\UsulanKenaikanPangkat\UsulanKenaikanPangkatFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\ModelStates\HasStates;

class UsulanKenaikanPangkat extends Model
{
    use HasFactory, HasStates, HasUlids, LogsActivity, SoftDeletes;

    protected $table = 'usulan_kenaikan_pangkat';

    protected $fillable = [
        'pegawai_id',
        'ref_pangkat_asal_id',
        'ref_pangkat_tujuan_id',
        'tmt_pangkat_asal',
        'periode_usul_bulan',
        'periode_usul_tahun',
        'nomor_usulan',
        'tanggal_usulan',
        'state',
        'catatan_pengusul',
        'catatan_penolakan',
        'nomor_sk',
        'tanggal_sk',
        'sk_file_path',
        'sk_file_original_name',
        'submitted_at',
        'finalized_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tmt_pangkat_asal' => 'date',
            'periode_usul_bulan' => 'integer',
            'periode_usul_tahun' => 'integer',
            'tanggal_usulan' => 'date',
            'tanggal_sk' => 'date',
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
            'state' => UsulanKenaikanPangkatState::class,
        ];
    }

    protected static function newFactory(): UsulanKenaikanPangkatFactory
    {
        return UsulanKenaikanPangkatFactory::new();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('usulan_kenaikan_pangkat');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function pangkatAsal(): BelongsTo
    {
        return $this->belongsTo(RefPangkat::class, 'ref_pangkat_asal_id');
    }

    public function pangkatTujuan(): BelongsTo
    {
        return $this->belongsTo(RefPangkat::class, 'ref_pangkat_tujuan_id');
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(UsulanKpApprovalStep::class, 'usulan_kenaikan_pangkat_id');
    }

    public function approverHistory(): HasMany
    {
        return $this->hasMany(UsulanKpApproverHistory::class, 'usulan_kenaikan_pangkat_id');
    }

    public function stateHistory(): HasMany
    {
        return $this->hasMany(UsulanKpStateHistory::class, 'usulan_kenaikan_pangkat_id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(UsulanKpLampiran::class, 'usulan_kenaikan_pangkat_id');
    }

    public function pdfs(): HasMany
    {
        return $this->hasMany(UsulanKpPdf::class, 'usulan_kenaikan_pangkat_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'created_by');
    }

    public function checklistSubmission(): MorphOne
    {
        return $this->morphOne(BerkasChecklistSubmission::class, 'subject');
    }
}
