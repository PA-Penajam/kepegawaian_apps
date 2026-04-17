<?php

namespace App\Models;

use App\Enums\StatusPengajuanPerubahanData;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanPerubahanData extends Model
{
    use HasFactory;
    use HasUlids;

    protected $table = 'pengajuan_perubahan_data';

    protected $fillable = [
        'nomor_pengajuan',
        'pengaju_id',
        'subject_pegawai_id',
        'validator_id',
        'jenis_pengaju',
        'domain',
        'aksi',
        'scope_key',
        'target_type',
        'target_id',
        'status',
        'before_payload',
        'after_payload',
        'changed_fields',
        'lampiran_paths',
        'alasan_penolakan',
        'submitted_at',
        'approved_at',
        'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'before_payload' => 'array',
            'after_payload' => 'array',
            'changed_fields' => 'array',
            'lampiran_paths' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'status' => StatusPengajuanPerubahanData::class,
        ];
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pengaju_id');
    }

    public function subjectPegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'subject_pegawai_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'validator_id');
    }
}
