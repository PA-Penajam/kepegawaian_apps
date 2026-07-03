<?php

use App\Http\Controllers\EmergencyLoginController;
use App\Http\Controllers\KeycloakAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Keycloak Authentication Routes
|--------------------------------------------------------------------------
|
| Routes untuk autentikasi OIDC melalui Keycloak. Mencakup login
| (redirect ke Keycloak), callback (token exchange), dan logout.
|
*/

Route::prefix('keycloak')->name('keycloak.')->group(function () {
    Route::get('/login', [KeycloakAuthController::class, 'login'])->name('login');
    Route::get('/callback', [KeycloakAuthController::class, 'callback'])->name('callback');
    Route::post('/logout', [KeycloakAuthController::class, 'logout'])->name('logout');
});

/*
|--------------------------------------------------------------------------
| Emergency Bypass Routes
|--------------------------------------------------------------------------
|
| Routes untuk akses darurat admin saat Keycloak tidak tersedia
| (circuit breaker OPEN). Login emergency menggunakan kredensial
| yang dikonfigurasi di environment.
|
*/

Route::prefix('emergency')->name('emergency.')->group(function () {
    Route::get('/login', [EmergencyLoginController::class, 'showLoginForm'])->name('login.form');
    Route::post('/login', [EmergencyLoginController::class, 'login'])->name('login');
});
