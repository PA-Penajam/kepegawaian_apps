<?php

use App\Http\Controllers\Auth\SsoAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SSO PA Penajam Authentication Routes
|--------------------------------------------------------------------------
|
| Routes untuk autentikasi OAuth 2.0 via Identity Provider SSO PA Penajam.
| Mendukung alur Authorization Code Grant + PKCE (RFC 7636).
|
*/

Route::prefix('auth/sso')->name('auth.sso.')->group(function () {
    Route::get('/login', [SsoAuthController::class, 'login'])->name('login');
    Route::get('/callback', [SsoAuthController::class, 'callback'])->name('callback');
    Route::post('/logout', [SsoAuthController::class, 'logout'])->name('logout');
});
