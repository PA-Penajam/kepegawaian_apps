<?php

use Illuminate\Support\Facades\Blade;

it('aplikasi menggunakan locale Bahasa Indonesia secara default', function (): void {
    expect(config('app.locale'))->toBe('id')
        ->and(app()->getLocale())->toBe('id')
        ->and(config('app.faker_locale'))->toBe('id_ID');
});

it('root blade view merender atribut lang id', function (): void {
    $rendered = Blade::render('<html lang="{{ str_replace(\'_\', \'-\', app()->getLocale()) }}">');

    expect($rendered)->toBe('<html lang="id">');
});
