<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use App\Models\Pegawai;
use App\States\Cuti\PengajuanState;
use Database\Factories\Cuti\CutiPengajuanFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\ModelStates\HasStates;

class CutiPengajuan extends Model
{
    use HasActivityLogOptions, HasFactory, HasStates, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_pengajuan';

    protected $fillable = [
        'nomor_pengajuan',
        'pegawai_nip',
        'jenis_cuti_kode',
        'tanggal_mulai',
        'tanggal_selesai',
        'jumlah_hari_kerja',
        'alasan',
        'alamat_selama_cuti',
        'nomor_telp_selama_cuti',
        'state',
        'petugas_kepegawaian_snapshot_nip',
        'atasan_langsung_snapshot_nip',
        'pejabat_berwenang_snapshot_nip',
        'petugas_kepegawaian_current_nip',
        'atasan_langsung_current_nip',
        'pejabat_berwenang_current_nip',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'jumlah_hari_kerja' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'state' => PengajuanState::class,
        ];
    }

    protected static function newFactory(): CutiPengajuanFactory
    {
        return CutiPengajuanFactory::new();
    }

    /**
     * Menghitung tahun hak berdasarkan tanggal mulai cuti.
     */
    public function tahunHak(): int
    {
        return $this->tanggal_mulai?->year ?? now()->year;
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_nip', 'nip');
    }

    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(CutiJenisMaster::class, 'jenis_cuti_kode', 'kode');
    }

    public function atasanLangsungSnapshot(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'atasan_langsung_snapshot_nip', 'nip');
    }

    public function atasanLangsungCurrent(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'atasan_langsung_current_nip', 'nip');
    }

    public function pejabatBerwenangCurrent(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pejabat_berwenang_current_nip', 'nip');
    }

    public function petugasKepegawaianCurrent(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'petugas_kepegawaian_current_nip', 'nip');
    }

    public function saldoLedger(): HasMany
    {
        return $this->hasMany(CutiSaldoLedger::class, 'pengajuan_id');
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(CutiPengajuanLampiran::class, 'pengajuan_id');
    }

    public function approvalSteps(): HasMany
    {
        return $this->hasMany(CutiPengajuanApprovalStep::class, 'pengajuan_id');
    }

    public function approverHistory(): HasMany
    {
        return $this->hasMany(CutiPengajuanApproverHistory::class, 'pengajuan_id');
    }

    public function stateHistory(): HasMany
    {
        return $this->hasMany(CutiPengajuanStateHistory::class, 'pengajuan_id');
    }

    public function pdf(): HasMany
    {
        return $this->hasMany(CutiPengajuanPdf::class, 'pengajuan_id');
    }
}
