<?php

namespace App\Models\Cuti;

use App\Enums\JenisJabatan;
use App\Models\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CutiKewenangan extends Model
{
    use HasUlids;

    protected $table = 'cuti_kewenangan';

    protected $fillable = [
        'jenis_cuti_kode',
        'jenis_jabatan',
        'pemberi_izin',
        'instansi_nama',
        'nama_aplikasi',
        'url_aplikasi',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'jenis_jabatan' => JenisJabatan::class,
            'aktif' => 'boolean',
        ];
    }

    /**
     * Master jenis cuti terkait aturan kewenangan ini.
     *
     * @return BelongsTo<CutiJenisMaster, $this>
     */
    public function jenisCuti(): BelongsTo
    {
        return $this->belongsTo(CutiJenisMaster::class, 'jenis_cuti_kode', 'kode');
    }
}
