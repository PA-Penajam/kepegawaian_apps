<?php

namespace App\Models;

use App\Enums\JenjangPendidikan;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class RiwayatPendidikan extends Model
{
    use HasFactory, HasUlids, LogsActivity, SoftDeletes;

    protected $table = 'riwayat_pendidikan';

    protected $fillable = [
        'pegawai_id',
        'jenjang',
        'nama_sekolah',
        'jurusan',
        'tahun_lulus',
        'no_ijazah',
        'tanggal_ijazah',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'jenjang' => JenjangPendidikan::class,
            'tanggal_ijazah' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->logFillable()
            ->setDescriptionForEvent(fn (string $eventName) => $eventName);
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }
}
