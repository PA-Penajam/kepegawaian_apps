<?php

namespace App\Models;

use App\Models\Concerns\HasActivityLogOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class HukumanDisiplin extends Model
{
    use HasActivityLogOptions, HasFactory, HasUlids, LogsActivity, SoftDeletes {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'hukuman_disiplin';

    protected $fillable = [
        'pegawai_id',
        'ref_jenis_hukuman_disiplin_id',
        'no_sk',
        'tanggal_sk',
        'tmt_berlaku',
        'tmt_selesai',
        'pelanggaran',
        'pejabat_penetap',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_sk' => 'date',
            'tmt_berlaku' => 'date',
            'tmt_selesai' => 'date',
            'deleted_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class);
    }

    public function jenisHukumanDisiplin(): BelongsTo
    {
        return $this->belongsTo(RefJenisHukumanDisiplin::class, 'ref_jenis_hukuman_disiplin_id');
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where(function (Builder $subQuery): void {
            $subQuery
                ->whereNull('tmt_selesai')
                ->orWhere('tmt_selesai', '>=', now()->toDateString());
        });
    }
}
