<?php

namespace App\Models;

use Database\Factories\RefPermissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RefPermission extends Model
{
    /** @use HasFactory<RefPermissionFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'ref_permissions';

    protected $fillable = ['nama', 'group', 'keterangan'];

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime'];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(RefRole::class, 'ref_role_permission', 'ref_permission_id', 'ref_role_id');
    }
}
