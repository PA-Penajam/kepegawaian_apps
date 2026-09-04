<?php

use Illuminate\Support\Facades\Route;

test('seluruh route reset password lokal dinonaktifkan', function () {
    expect(Route::has('password.request'))->toBeFalse()
        ->and(Route::has('password.email'))->toBeFalse()
        ->and(Route::has('password.reset'))->toBeFalse()
        ->and(Route::has('password.update'))->toBeFalse();
});
