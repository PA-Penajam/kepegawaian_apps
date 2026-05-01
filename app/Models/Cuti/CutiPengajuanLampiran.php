<?php

namespace App\Models\Cuti;

use App\Models\Concerns\HasActivityLogOptions;
use App\Models\Model;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class CutiPengajuanLampiran extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'cuti_pengajuan_lampiran';

    protected $fillable = [
        'pengajuan_id',
        'jenis_lampiran',
        'nama_file_asli',
        'path_file',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'uploaded_by_nip',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(CutiPengajuan::class, 'pengajuan_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'uploaded_by_nip', 'nip');
    }
}
