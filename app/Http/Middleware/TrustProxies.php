<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;

/**
 * Mengatur daftar reverse proxy terpercaya dan menormalisasi skema HTTPS
 * dari header Cloudflare (`CF-Visitor`) ketika request melewati proxy terpercaya.
 */
class TrustProxies extends BaseTrustProxies
{
    /**
     * Mempercayai header proxy yang dikonfigurasi lalu menyesuaikan protokol
     * dari `CF-Visitor` jika reverse proxy lokal menimpanya dengan `http`.
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $request::setTrustedProxies([], $this->getTrustedHeaderNames());

        $this->setTrustedProxyIpAddresses($request);

        if ($request->isFromTrustedProxy()) {
            $cfVisitor = trim((string) $request->headers->get('CF-Visitor', ''));

            if (preg_match('/^\{\s*"scheme"\s*:\s*"https"\s*\}$/i', $cfVisitor) === 1) {
                $request->headers->set('X-Forwarded-Proto', 'https');
                $request->headers->set('X-Forwarded-Port', '443');
            }
        }

        return $next($request);
    }

    /**
     * Mengambil daftar IP/CIDR proxy terpercaya dari `app.trusted_proxies`
     * secara fail-closed ketika nilainya kosong.
     *
     * @return array<int, string>|string|null
     */
    protected function proxies(): array|string|null
    {
        if (static::$alwaysTrustProxies !== null) {
            return static::$alwaysTrustProxies;
        }

        $configured = config('app.trusted_proxies');

        if ($configured === null) {
            return null;
        }

        if (is_array($configured)) {
            $proxies = array_values(array_filter(
                array_map(static fn (mixed $item): string => trim((string) $item), $configured),
                static fn (string $item): bool => $item !== ''
            ));

            if ($proxies === []) {
                return null;
            }

            return $proxies === ['*'] ? '*' : $proxies;
        }

        $raw = trim((string) $configured);

        if ($raw === '') {
            return null;
        }

        if ($raw === '*') {
            return '*';
        }

        $proxies = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn (string $item): bool => $item !== '' && $item !== '*' && $item !== '**'
        ));

        return $proxies === [] ? null : $proxies;
    }
}
