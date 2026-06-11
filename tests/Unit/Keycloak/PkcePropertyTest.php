<?php

/**
 * Property-Based Test: PKCE Integrity
 *
 * Memvalidasi bahwa PKCE pair yang dihasilkan oleh PkceGenerator
 * selalu memenuhi properti keamanan RFC 7636 untuk SEMUA generasi,
 * bukan hanya contoh tertentu.
 *
 * Validates: Requirements 1.1, 11.1, 11.2, 11.3, 11.4
 */

use App\Keycloak\Services\PkceGenerator;

const PKCE_PROPERTY_ITERATIONS = 100;

describe('Property 1: PKCE Integrity', function () {
    beforeEach(function () {
        $this->generator = new PkceGenerator;
    });

    /**
     * Validates: Requirement 11.1
     *
     * FOR ALL generated PKCE pairs, code_verifier length
     * harus selalu antara 43-128 karakter.
     */
    test('code_verifier length selalu antara 43-128 karakter untuk semua generasi', function () {
        for ($i = 0; $i < PKCE_PROPERTY_ITERATIONS; $i++) {
            $pair = $this->generator->generate();
            $length = strlen($pair->verifier);

            expect($length)
                ->toBeGreaterThanOrEqual(43, "Iterasi {$i}: verifier terlalu pendek ({$length} karakter)")
                ->toBeLessThanOrEqual(128, "Iterasi {$i}: verifier terlalu panjang ({$length} karakter)");
        }
    });

    /**
     * Validates: Requirement 11.2
     *
     * FOR ALL generated PKCE pairs, code_verifier hanya boleh
     * mengandung karakter base64url valid [A-Za-z0-9\-_] tanpa padding.
     */
    test('code_verifier hanya mengandung karakter base64url valid tanpa padding untuk semua generasi', function () {
        for ($i = 0; $i < PKCE_PROPERTY_ITERATIONS; $i++) {
            $pair = $this->generator->generate();

            // Harus match regex base64url tanpa padding
            expect($pair->verifier)
                ->toMatch('/^[A-Za-z0-9\-_]+$/', "Iterasi {$i}: verifier mengandung karakter tidak valid");

            // Tidak boleh ada padding '='
            expect($pair->verifier)
                ->not->toContain('=', "Iterasi {$i}: verifier mengandung padding character");

            // Tidak boleh ada karakter base64 standard '+' atau '/'
            expect($pair->verifier)
                ->not->toContain('+', "Iterasi {$i}: verifier mengandung '+' (bukan base64url)")
                ->not->toContain('/', "Iterasi {$i}: verifier mengandung '/' (bukan base64url)");
        }
    });

    /**
     * Validates: Requirement 11.3
     *
     * FOR ALL generated PKCE pairs, code_challenge harus selalu
     * sama dengan BASE64URL(SHA256(code_verifier)).
     */
    test('code_challenge selalu equals BASE64URL(SHA256(code_verifier)) untuk semua generasi', function () {
        for ($i = 0; $i < PKCE_PROPERTY_ITERATIONS; $i++) {
            $pair = $this->generator->generate();

            // Hitung ulang challenge dari verifier secara independen
            $expectedChallenge = strtr(
                rtrim(base64_encode(hash('sha256', $pair->verifier, true)), '='),
                '+/',
                '-_'
            );

            expect($pair->challenge)->toBe(
                $expectedChallenge,
                "Iterasi {$i}: challenge tidak sesuai dengan SHA256(verifier)"
            );
        }
    });

    /**
     * Validates: Requirement 11.4
     *
     * FOR ALL generated PKCE pairs, method harus selalu 'S256',
     * tidak pernah 'plain' atau nilai lain.
     */
    test('method selalu S256 dan tidak pernah plain untuk semua generasi', function () {
        for ($i = 0; $i < PKCE_PROPERTY_ITERATIONS; $i++) {
            $pair = $this->generator->generate();

            expect($pair->method)
                ->toBe('S256', "Iterasi {$i}: method bukan S256")
                ->not->toBe('plain', "Iterasi {$i}: method menggunakan plain (tidak aman)");
        }
    });

    /**
     * Validates: Requirements 1.1, 11.1
     *
     * FOR ALL generated PKCE pairs, setiap verifier harus unik.
     * Ini membuktikan penggunaan CSPRNG yang menghasilkan output
     * non-deterministik.
     */
    test('setiap generated pair menghasilkan verifier unik (CSPRNG)', function () {
        $verifiers = [];

        for ($i = 0; $i < PKCE_PROPERTY_ITERATIONS; $i++) {
            $pair = $this->generator->generate();
            $verifiers[] = $pair->verifier;
        }

        $uniqueVerifiers = array_unique($verifiers);

        expect(count($uniqueVerifiers))->toBe(
            PKCE_PROPERTY_ITERATIONS,
            'Ditemukan verifier duplikat — CSPRNG mungkin tidak berfungsi dengan benar'
        );
    });
});
