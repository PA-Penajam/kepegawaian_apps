<?php

namespace App\Models\UsulanKenaikanPangkat;

use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class UsulanKpPdf extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'usulan_kp_pdf';

    protected $fillable = [
        'usulan_kenaikan_pangkat_id',
        'jenis_pdf',
        'nomor_surat',
        'file_path',
        'generated_by',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
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

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'generated_by');
    }
}
