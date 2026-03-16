<?php

namespace App\Models;

use Database\Factories\RefRoleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefRole extends Model
{
    /** @use HasFactory<RefRoleFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_roles';

    protected $fillable = ['nama', 'keterangan', 'is_system'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function pegawai(): BelongsToMany
    {
        return $this->belongsToMany(Pegawai::class, 'pegawai_role', 'ref_role_id', 'pegawai_id')
            ->withPivot('created_at');
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(RefPermission::class, 'ref_role_permission', 'ref_role_id', 'ref_permission_id');
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions()->where('nama', $permissionName)->exists();
    }
}
