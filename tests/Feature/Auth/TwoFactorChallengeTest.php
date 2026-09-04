<?php

use Illuminate\Support\Facades\Route;

test('seluruh route two factor lokal dinonaktifkan', function () {
    expect(Route::has('two-factor.login'))->toBeFalse()
        ->and(Route::has('two-factor.login.store'))->toBeFalse()
        ->and(Route::has('two-factor.enable'))->toBeFalse()
        ->and(Route::has('two-factor.confirm'))->toBeFalse()
        ->and(Route::has('two-factor.disable'))->toBeFalse();
});
