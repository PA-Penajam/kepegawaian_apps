<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
                    'roles' => $user->roles->pluck('nama')->toArray(),
                    'permissions' => $user->roles->flatMap(
                        fn ($role) => $role->permissions->pluck('nama')
                    )->unique()->values()->toArray(),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                ] : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
