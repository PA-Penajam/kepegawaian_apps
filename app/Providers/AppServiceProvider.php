<?php

namespace App\Providers;

use App\Models\Pegawai;
use App\Policies\PegawaiPolicy;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Pegawai::class, PegawaiPolicy::class);

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
                    'time_ms'  => $query->time,
                ]);
            }
        });
    }
}
