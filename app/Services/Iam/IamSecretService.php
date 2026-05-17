<?php

namespace App\Services\Iam;

use App\Models\IamApplication;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class IamSecretService
{
    private const CACHE_KEY_PREFIX = 'iam:secret:recovery:';
    private const CACHE_TTL_MINUTES = 15;
    private const ACTIVITY_LOG_NAME = 'iam_audit';

    public function __construct(
        private readonly CacheRepository $cache,
    ) {}

    public function generateAndStore(IamApplication $app, ?Request $request = null): string
    {
        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $app->api_key = $key;
        $app->api_secret_hash = $hash;
        $app->save();

        $this->putRecoveryCache($app, $secret);

        $this->logAudit('secret.created', $app, $request, [
            'app_slug' => $app->slug,
        ]);

        return $secret;
    }

    public function regenerate(IamApplication $app, ?Request $request = null): string
    {
        $previousKey = $app->api_key ?? '';
        $previousKeyPrefix = substr($previousKey, 0, 8);

        ['key' => $key, 'secret' => $secret, 'hash' => $hash] = IamApplication::generateApiCredentials();

        $app->api_key = $key;
        $app->api_secret_hash = $hash;
        $app->save();

        $this->putRecoveryCache($app, $secret);

        $this->logAudit('secret.regenerated', $app, $request, [
            'app_slug' => $app->slug,
            'previous_key_prefix' => $previousKeyPrefix,
        ]);

        return $secret;
    }

    public function recoverFromCache(IamApplication $app, ?Request $request = null): ?string
    {
        $plaintext = $this->cache->get($this->cacheKey($app));

        if ($plaintext === null) {
            return null;
        }

        $this->logAudit('secret.recovery_viewed', $app, $request, [
            'app_slug' => $app->slug,
            'ttl_remaining_seconds' => $this->getRecoveryTtlSeconds($app),
        ]);

        return $plaintext;
    }

    public function invalidateRecovery(IamApplication $app, ?Request $request = null): void
    {
        $this->cache->forget($this->cacheKey($app));

        $this->logAudit('secret.recovery_acknowledged', $app, $request, [
            'app_slug' => $app->slug,
        ]);
    }

    public function hasRecoverableSecret(IamApplication $app): bool
    {
        return $this->cache->has($this->cacheKey($app));
    }

    public function getRecoveryTtlSeconds(IamApplication $app): int
    {
        $expiresAt = \DB::table('cache')
            ->where('key', config('cache.prefix') . $this->cacheKey($app))
            ->value('expiration');

        if ($expiresAt === null) {
            return 0;
        }

        $remaining = (int) $expiresAt - time();

        return max(0, $remaining);
    }

    private function cacheKey(IamApplication $app): string
    {
        return self::CACHE_KEY_PREFIX . $app->id;
    }

    private function putRecoveryCache(IamApplication $app, string $secret): void
    {
        try {
            $this->cache->put(
                $this->cacheKey($app),
                $secret,
                now()->addMinutes(self::CACHE_TTL_MINUTES),
            );
        } catch (\Throwable $e) {
            logger()->warning('IAM secret recovery cache write failed', [
                'app_id' => $app->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function logAudit(string $event, IamApplication $app, ?Request $request, array $extraProps = []): void
    {
        $baseProps = [
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ];

        activity(self::ACTIVITY_LOG_NAME)
            ->performedOn($app)
            ->causedBy($request?->user())
            ->event($event)
            ->withProperties(array_merge($baseProps, $extraProps))
            ->log($event);
    }
}
