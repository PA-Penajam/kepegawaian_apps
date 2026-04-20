<?php

namespace App\Http\Middleware;

use App\Enums\StatusPengajuanPerubahanData;
use App\Models\PengajuanPerubahanData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        // Eager load permissions sekaligus untuk mencegah N+1 per role
        $user?->loadMissing('iamRoles.permissions');

        $permissions = $user
            ? $user->iamRoles->flatMap(
                fn ($role) => $role->permissions->pluck('slug')
            )->unique()->values()->toArray()
            : [];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'nip' => $user->nip,
                    'email' => $user->email,
                    'foto' => $user->foto ?? null,
                    'email_verified_at' => $user->email_verified_at,
                    'two_factor_enabled' => ! is_null($user->two_factor_confirmed_at),
                    'roles' => $user->iamRoles->pluck('nama')->toArray(),
                    'permissions' => $permissions,
                    'pending_pengajuan_count' => in_array('pengajuan-perubahan.validate', $permissions, true)
                        ? Cache::remember('pending_pengajuan_count', 60, fn () => PengajuanPerubahanData::query()->where('status', StatusPengajuanPerubahanData::Pending)->count())
                        : 0,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ];
    }
}
