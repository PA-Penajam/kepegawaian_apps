<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLogOptions;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class DokumenPegawai extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity, SoftDeletes {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'dokumen_pegawai';

    protected $fillable = [
        'pegawai_id',
        'ref_jenis_dokumen_id',
        'jenis_dokumen',
        'nomor_dokumen',
        'tanggal_dokumen',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_dokumen' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function jenisDokumen(): BelongsTo
    {
        return $this->belongsTo(RefJenisDokumen::class, 'ref_jenis_dokumen_id');
    }
}
