<?php

namespace App\Providers;

use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use App\Policies\Cuti\CutiPengajuanPolicy;
use App\Policies\PegawaiPolicy;
use App\Policies\PengajuanPerubahanDataPolicy;
use App\Services\Cuti\Rules\CutiAlasanPentingRule;
use App\Services\Cuti\Rules\CutiBesarRule;
use App\Services\Cuti\Rules\CutiLtnRule;
use App\Services\Cuti\Rules\CutiMelahirkanRule;
use App\Services\Cuti\Rules\CutiRuleEngine;
use App\Services\Cuti\Rules\CutiSakitTier1Rule;
use App\Services\Cuti\Rules\CutiSakitTier2Rule;
use App\Services\Cuti\Rules\CutiTahunanRule;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
        Gate::policy(Pegawai::class, PegawaiPolicy::class);
        Gate::policy(PengajuanPerubahanData::class, PengajuanPerubahanDataPolicy::class);

        $this->configureDefaults();
        $this->registerSlowQueryLogger();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        // Mengkonfigurasi format serialisasi tanggal ke 'Y-m-d'
        Date::serializeUsing(fn (CarbonInterface $date) => $date->format('Y-m-d'));

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

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
}
