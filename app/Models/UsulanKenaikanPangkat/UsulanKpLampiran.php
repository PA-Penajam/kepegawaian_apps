<?php

namespace App\Models\UsulanKenaikanPangkat;

use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class UsulanKpLampiran extends Model
{
    use HasFactory, HasUlids, LogsActivity;

    protected $table = 'usulan_kp_lampiran';

    protected $fillable = [
        'usulan_kenaikan_pangkat_id',
        'jenis',
        'nama_file',
        'file_path',
        'file_original_name',
        'file_mime',
        'file_size',
        'uploaded_by',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
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

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'uploaded_by');
    }
}
