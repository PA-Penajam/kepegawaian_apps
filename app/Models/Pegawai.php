<?php

namespace App\Models;

use App\Enums\Agama;
use App\Enums\GolonganDarah;
use App\Enums\JenisKelamin;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Models\Concerns\HasActivityLogOptions;
use App\Traits\Filterable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\Models\Concerns\LogsActivity;

class Pegawai extends Authenticatable implements FilamentUser, HasName
{
    use Filterable, HasActivityLogOptions, HasApiTokens, HasFactory, HasUlids, LogsActivity, Notifiable, SoftDeletes, TwoFactorAuthenticatable {
        HasActivityLogOptions::getActivitylogOptions insteadof LogsActivity;
    }

    protected $table = 'pegawai';

    protected $appends = ['foto_url'];

    protected $fillable = [
        'nip', 'nip_lama', 'nama_lengkap', 'tempat_lahir', 'tanggal_lahir',
        'jenis_kelamin', 'agama', 'status_perkawinan', 'golongan_darah',
        'alamat', 'no_telepon', 'email', 'status_kepegawaian', 'status_pegawai',
        'tmt_cpns', 'tmt_pns', 'pendidikan_terakhir', 'tanggal_masuk',
        'tanggal_pensiun_bup', 'ref_pangkat_id', 'ref_jabatan_id', 'ref_unit_kerja_id',
        'no_karpeg', 'no_karis_karsu', 'npwp', 'no_bpjs_kesehatan',
        'no_bpjs_ketenagakerjaan', 'no_taspen', 'foto', 'keterangan',
        'password',
        'keycloak_id', 'keycloak_synced_at', 'keycloak_user_id',
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
            'keycloak_synced_at' => 'datetime',
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

    // === Relasi IAM (many-to-many via iam_user_roles) ===

    public function iamUserRoles(): HasMany
    {
        return $this->hasMany(IamUserRole::class, 'user_id');
    }

    public function iamRoles(): BelongsToMany
    {
        return $this->belongsToMany(IamRole::class, 'iam_user_roles', 'user_id', 'iam_role_id')
            ->withPivot('assigned_at');
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

    // === Permission methods (IAM-aware) ===

    private ?array $cachedPermissions = null;

    private function loadPermissions(): array
    {
        if ($this->cachedPermissions === null) {
            $this->cachedPermissions = $this->iamRoles()
                ->with('permissions')
                ->get()
                ->flatMap(fn ($role) => $role->permissions->pluck('slug'))
                ->unique()
                ->values()
                ->all();
        }

        return $this->cachedPermissions;
    }

    public function clearPermissionCache(): void
    {
        $this->cachedPermissions = null;
    }

    public function refresh(): static
    {
        $this->clearPermissionCache();

        return parent::refresh();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->loadPermissions(), true);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        $loaded = $this->loadPermissions();

        foreach ($permissions as $permission) {
            if (in_array($permission, $loaded, true)) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->iamRoles()->where('slug', 'admin')->exists();
    }

    public function isOperator(): bool
    {
        return $this->iamRoles()->where('slug', 'operator')->exists();
    }

    public function isViewer(): bool
    {
        return $this->iamRoles()->where('slug', 'viewer')->exists();
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

    protected function fotoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->foto !== null
                ? Storage::disk('public')->url($this->foto)
                : null,
        );
    }

    public function getNamaPangkatLengkapAttribute(): string
    {
        if ($this->pangkat === null) {
            return '';
        }

        return sprintf('%s - %s', $this->pangkat->nama, $this->pangkat->kode);
    }

    /**
     * Menentukan apakah user dapat mengakses Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Nama yang ditampilkan di Filament admin panel.
     */
    public function getFilamentName(): string
    {
        return $this->nama_lengkap ?? '';
    }
}
