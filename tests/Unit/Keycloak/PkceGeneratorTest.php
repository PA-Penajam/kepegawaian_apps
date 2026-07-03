<?php

use App\Keycloak\DataTransferObjects\PkcePair;
use App\Keycloak\Services\PkceGenerator;

beforeEach(function () {
    $this->generator = new PkceGenerator;
});

test('generate mengembalikan PkcePair instance', function () {
    $pair = $this->generator->generate();

    expect($pair)->toBeInstanceOf(PkcePair::class);
});

test('code_verifier memiliki panjang antara 43-128 karakter', function () {
    $pair = $this->generator->generate();

    expect(strlen($pair->verifier))
        ->toBeGreaterThanOrEqual(43)
        ->toBeLessThanOrEqual(128);
});

test('code_verifier hanya berisi karakter base64url valid tanpa padding', function () {
    $pair = $this->generator->generate();

    // Hanya A-Z, a-z, 0-9, hyphen, underscore (tanpa '=' padding)
    expect($pair->verifier)->toMatch('/^[A-Za-z0-9\-_]+$/');
});

test('code_challenge adalah BASE64URL(SHA256(code_verifier))', function () {
    $pair = $this->generator->generate();

    // Hitung ulang challenge dari verifier
    $expectedChallenge = strtr(
        rtrim(base64_encode(hash('sha256', $pair->verifier, true)), '='),
        '+/',
        '-_'
    );

    expect($pair->challenge)->toBe($expectedChallenge);
});

test('method selalu S256', function () {
    $pair = $this->generator->generate();

    expect($pair->method)->toBe('S256');
});

test('setiap generate menghasilkan verifier yang unik', function () {
    $pairs = collect(range(1, 10))->map(fn () => $this->generator->generate());

    $verifiers = $pairs->pluck('verifier')->unique();

    expect($verifiers)->toHaveCount(10);
});

test('code_challenge tidak mengandung padding character', function () {
    $pair = $this->generator->generate();

    expect($pair->challenge)->not->toContain('=');
});

test('code_verifier tidak mengandung karakter + atau /', function () {
    $pair = $this->generator->generate();

    expect($pair->verifier)
        ->not->toContain('+')
        ->not->toContain('/');
});
