<?php

use App\Services\Sso\Services\PkceGenerator;

describe('PkceGenerator', function () {
    test('menghasilkan PKCE pair yang valid sesuai RFC 7636', function () {
        $generator = new PkceGenerator;
        $pkce = $generator->generate();

        expect($pkce->method)->toBe('S256');
        expect($pkce->verifier)->toBeString()->not->toBeEmpty();
        expect($pkce->challenge)->toBeString()->not->toBeEmpty();

        // Verifier harus berupa base64url string tanpa padding (A-Z, a-z, 0-9, -, _)
        expect($pkce->verifier)->toMatch('/^[A-Za-z0-9\-_]+$/');
        expect($pkce->challenge)->toMatch('/^[A-Za-z0-9\-_]+$/');

        // Panjang verifier minimum 43 karakter sesuai RFC 7636
        expect(strlen($pkce->verifier))->toBeGreaterThanOrEqual(43);

        // Challenge harus sama dengan SHA256(verifier) di-encode base64url
        $expectedChallenge = $generator->base64UrlEncode(
            hash('sha256', $pkce->verifier, true)
        );

        expect($pkce->challenge)->toBe($expectedChallenge);
    });

    test('menghasilkan verifier yang unik di setiap pemanggilan', function () {
        $generator = new PkceGenerator;
        $pair1 = $generator->generate();
        $pair2 = $generator->generate();

        expect($pair1->verifier)->not->toBe($pair2->verifier);
        expect($pair1->challenge)->not->toBe($pair2->challenge);
    });
});
