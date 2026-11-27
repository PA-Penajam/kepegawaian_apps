<?php

namespace App\Providers;

use App\Events\ChecklistKelengkapanBerubah;
use App\Events\UsulanKenaikanPangkat\UsulanKpSkTerbit;
use App\Listeners\UsulanKenaikanPangkat\SinkronkanRiwayatPangkat;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Policies\ChecklistTemplatePolicy;
use App\Policies\Cuti\CutiPengajuanPolicy;
use App\Policies\PegawaiPolicy;
use App\Policies\PengajuanPerubahanDataPolicy;
use App\Policies\UsulanKenaikanPangkatPolicy;
use App\Services\Cuti\Rules\CutiAlasanPentingRule;
use App\Services\Cuti\Rules\CutiBesarRule;
use App\Services\Cuti\Rules\CutiLtnRule;
use App\Services\Cuti\Rules\CutiMelahirkanRule;
use App\Services\Cuti\Rules\CutiRuleEngine;
use App\Services\Cuti\Rules\CutiSakitTier1Rule;
use App\Services\Cuti\Rules\CutiSakitTier2Rule;
use App\Services\Cuti\Rules\CutiTahunanRule;
use App\Services\NomorSurat\DatabaseNomorSuratService;
use App\Services\NomorSurat\NomorSuratService;
use App\Services\Sikep\NullSikepAdapter;
use App\Services\Sikep\SikepAdapter;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\FakePenomoranGateway;
use App\Services\SuratDinas\SisukmaPenomoranGateway;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SikepAdapter::class, NullSikepAdapter::class);

        $this->app->bind(NomorSuratService::class, DatabaseNomorSuratService::class);

        $this->app->bind(PenomoranSuratGateway::class, function ($app) {
            return config('sisukma.enabled')
                ? $app->make(SisukmaPenomoranGateway::class)
                : new FakePenomoranGateway;
        });

        $this->app->bind(CutiRuleEngine::class, fn () => new CutiRuleEngine([
            app(CutiTahunanRule::class),
            app(CutiSakitTier1Rule::class),
            app(CutiSakitTier2Rule::class),
            app(CutiAlasanPentingRule::class),
            app(CutiBesarRule::class),
            app(CutiMelahirkanRule::class),
            app(CutiLtnRule::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(CutiPengajuan::class, CutiPengajuanPolicy::class);
        Gate::policy(BerkasChecklistTemplate::class, ChecklistTemplatePolicy::class);
        Gate::policy(Pegawai::class, PegawaiPolicy::class);
        Gate::policy(PengajuanPerubahanData::class, PengajuanPerubahanDataPolicy::class);
        Gate::policy(UsulanKenaikanPangkat::class, UsulanKenaikanPangkatPolicy::class);

        $this->configureDefaults();
        $this->registerStorageDrivers();
        $this->registerEventListeners();
        $this->registerSlowQueryLogger();
        $this->registerRateLimiters();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        if (app()->environment('production') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');

            if (in_array(config('session.secure'), [null, ''], true)) {
                config(['session.secure' => true]);
            }
        }

        Date::use(CarbonImmutable::class);

        // Mengkonfigurasi format serialisasi tanggal ke 'Y-m-d'
        Date::serializeUsing(fn (CarbonInterface $date) => $date->format('Y-m-d'));

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Model::preventLazyLoading(! app()->isProduction());

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Mendaftarkan listener untuk query lambat.
     * Query yang melebihi threshold akan dicatat sebagai warning.
     */
    private function registerSlowQueryLogger(): void
    {
        if (! config('app.log_slow_queries', false)) {
            return;
        }

        $threshold = config('app.slow_query_threshold_ms', 500);

        DB::listen(function ($query) use ($threshold): void {
            if ($query->time >= $threshold) {
                logger()->warning('[SLOW QUERY] '.$query->time.'ms | '.$query->sql, [
                    'bindings' => $query->bindings,
                    'time_ms' => $query->time,
                ]);
            }
        });
    }

    private function registerEventListeners(): void
    {
        Event::listen(UsulanKpSkTerbit::class, SinkronkanRiwayatPangkat::class);

        Event::listen(ChecklistKelengkapanBerubah::class, function (ChecklistKelengkapanBerubah $event): void {
            unset($event);
        });
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('iam-regenerate', function (HttpRequest $request) {
            return Limit::perHour(5)
                ->by($request->user()?->id ?: $request->ip())
                ->response(function (HttpRequest $request, array $headers) {
                    return back()->with(
                        'error',
                        'Anda telah melampaui batas regenerasi kunci (5 per jam). Silakan coba lagi nanti.',
                    );
                });
        });
    }

    /**
     * Mendaftarkan driver filesystem kustom termasuk Google Drive OAuth 2.0.
     */
    private function registerStorageDrivers(): void
    {
        Storage::extend('google', function (Application $app, array $config): FilesystemAdapter {
            $options = [];

            if (filled($config['teamDriveId'] ?? null)) {
                $options['teamDriveId'] = $config['teamDriveId'];
            }

            $client = $app->make(GoogleClient::class);
            $client->setClientId((string) ($config['clientId'] ?? ''));
            $client->setClientSecret((string) ($config['clientSecret'] ?? ''));

            if (filled($config['refreshToken'] ?? null)) {
                $client->refreshToken((string) $config['refreshToken']);
            }

            $service = new GoogleDriveService($client);
            $folder = filled($config['folder'] ?? null) ? (string) $config['folder'] : '/';
            $adapter = new GoogleDriveAdapter($service, $folder, $options);
            $driver = new Filesystem($adapter, $config);

            return new FilesystemAdapter($driver, $adapter, $config);
        });
    }
}
