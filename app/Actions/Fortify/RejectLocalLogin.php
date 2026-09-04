<?php

namespace App\Actions\Fortify;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RejectLocalLogin
{
    /**
     * Tolak seluruh autentikasi kredensial lokal; autentikasi hanya melalui SSO.
     *
     * @throws ValidationException
     */
    public function handle(Request $request, Closure $next): never
    {
        throw ValidationException::withMessages([
            'nip' => 'Login lokal dinonaktifkan. Silakan masuk melalui SSO PA Penajam.',
        ]);
    }
}
