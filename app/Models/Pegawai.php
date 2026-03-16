<?php

namespace App\Models;

use App\Enums\Agama;
use App\Enums\GolonganDarah;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class Pegawai extends Authenticatable
{
    use Filterable, HasFactory, HasUlids, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $table = 'pegawai';

    protected $fillable = [
        'nip', 'nip_lama', 'nama_lengkap', 'tempat_lahir', 'tanggal_lahir',
        'jenis_kelamin', 'agama', 'status_perkawinan', 'golongan_darah',
        'alamat', 'no_telepon', 'email', 'status_kepegawaian', 'status_pegawai',
        'tmt_cpns', 'tmt_pns', 'pendidikan_terakhir', 'tanggal_masuk',
        'tanggal_pensiun_bup', 'ref_pangkat_id', 'ref_jabatan_id', 'ref_unit_kerja_id',
        'no_karpeg', 'no_karis_karsu', 'npwp', 'no_bpjs_kesehatan',
        'no_bpjs_ketenagakerjaan', 'no_taspen', 'foto', 'keterangan',
        'password',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
            'jenis_kelamin' => JenisKelamin::class,
            'agama' => Agama::class,
            'status_perkawinan' => StatusPerkawinan::class,
            'golongan_darah' => GolonganDarah::class,
            'status_kepegawaian' => StatusKepegawaian::class,
            'status_pegawai' => StatusPegawai::class,
            'tmt_cpns' => 'date',
            'tmt_pns' => 'date',
            'tanggal_masuk' => 'date',
            'tanggal_pensiun_bup' => 'date',
            'deleted_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'email_verified_at' => 'datetime',
        ];
    }

    // === Relasi referensi ===

    public function pangkat(): BelongsTo
    {
        return $this->belongsTo(RefPangkat::class, 'ref_pangkat_id');
    }

    public function jabatan(): BelongsTo
    {
        return $this->belongsTo(RefJabatan::class, 'ref_jabatan_id');
    }

    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(RefUnitKerja::class, 'ref_unit_kerja_id');
    }

    // === Relasi RBAC (many-to-many) ===

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RefRole::class, 'pegawai_role', 'pegawai_id', 'ref_role_id')
            ->withPivot('created_at');
    }

    // === Relasi riwayat ===

    public function riwayatJabatan(): HasMany
    {
        return $this->hasMany(RiwayatJabatan::class, 'pegawai_id');
    }

    public function riwayatDiklat(): HasMany
    {
        return $this->hasMany('App\\Models\\RiwayatDiklat', 'pegawai_id');
    }

    public function riwayatPendidikan(): HasMany
    {
        return $this->hasMany('App\\Models\\RiwayatPendidikan', 'pegawai_id');
    }

    public function riwayatPangkat(): HasMany
    {
        return $this->hasMany('App\\Models\\RiwayatPangkat', 'pegawai_id');
    }

    public function dokumenPegawai(): HasMany
    {
        return $this->hasMany(DokumenPegawai::class, 'pegawai_id');
    }

    public function keluarga(): HasMany
    {
        return $this->hasMany(Keluarga::class, 'pegawai_id');
    }

    public function penghargaan(): HasMany
    {
        return $this->hasMany(Penghargaan::class, 'pegawai_id');
    }

    public function hukumanDisiplin(): HasMany
    {
        return $this->hasMany(HukumanDisiplin::class, 'pegawai_id');
    }

    // === Permission methods (multi-role aware) ===

    public function hasPermission(string $permission): bool
    {
        return $this->roles()->whereHas('permissions', function (Builder $q) use ($permission) {
            $q->where('nama', $permission);
        })->exists();
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        return $this->roles()->whereHas('permissions', function (Builder $q) use ($permissions) {
            $q->whereIn('nama', $permissions);
        })->exists();
    }

    public function isAdmin(): bool
    {
        return $this->roles()->where('nama', 'Admin')->exists();
    }

    public function isOperator(): bool
    {
        return $this->roles()->where('nama', 'Operator')->exists();
    }

    public function isViewer(): bool
    {
        return $this->roles()->where('nama', 'Viewer')->exists();
    }

    // === Notifikasi ===

    public function routeNotificationForMail(): ?string
    {
        return $this->email;
    }

    // === Scopes ===

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status_pegawai', StatusPegawai::Aktif->value);
    }

    public function scopeByUnitKerja(Builder $query, string $id): Builder
    {
        return $query->where('ref_unit_kerja_id', $id);
    }

    public function scopeByGolongan(Builder $query, string $golongan): Builder
    {
        return $query->whereHas('pangkat', function (Builder $pangkatQuery) use ($golongan): void {
            $pangkatQuery
                ->where('kode', $golongan)
                ->orWhere('golongan', $golongan);
        });
    }

    // === Accessors ===

    public function getNamaPangkatLengkapAttribute(): string
    {
        if ($this->pangkat === null) {
            return '';
        }

        return sprintf('%s - %s', $this->pangkat->nama, $this->pangkat->kode);
    }
}
