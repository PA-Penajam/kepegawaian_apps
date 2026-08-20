<x-filament-panels::page>
    @php
        $syncState = $this->getSyncStateData();
        $circuitBreaker = $this->getCircuitBreakerData();
    @endphp

    {{-- Circuit Breaker Real-time Status Card --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <div @class([
                    'flex h-12 w-12 shrink-0 items-center justify-center rounded-xl',
                    'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-400' => $circuitBreaker['state'] === 'CLOSED',
                    'bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400' => $circuitBreaker['state'] === 'OPEN',
                    'bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400' => $circuitBreaker['state'] === 'HALF-OPEN' || $circuitBreaker['state'] === 'HALF_OPEN',
                    'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' => !in_array($circuitBreaker['state'], ['CLOSED', 'OPEN', 'HALF-OPEN', 'HALF_OPEN']),
                ])>
                    @if ($circuitBreaker['state'] === 'CLOSED')
                        <x-heroicon-o-shield-check class="h-7 w-7" />
                    @elseif ($circuitBreaker['state'] === 'OPEN')
                        <x-heroicon-o-exclamation-triangle class="h-7 w-7 animate-pulse" />
                    @else
                        <x-heroicon-o-arrow-path class="h-7 w-7 animate-spin" />
                    @endif
                </div>

                <div>
                    <div class="flex items-center gap-2.5">
                        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Status Circuit Breaker IAM & SSO</h2>
                        <x-filament::badge :color="$circuitBreaker['state_color']" size="sm">
                            {{ $circuitBreaker['state'] }}
                        </x-filament::badge>
                    </div>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        @if ($circuitBreaker['state'] === 'CLOSED')
                            Koneksi ke server IAM Keycloak beroperasi secara normal dan seluruh permintaan sinkronisasi diizinkan.
                        @elseif ($circuitBreaker['state'] === 'OPEN')
                            Circuit breaker dalam status OPEN. Seluruh sinkronisasi otomatis dialihkan untuk melindungi beban server. Gunakan tombol reset jika server telah pulih.
                        @else
                            Circuit breaker dalam status uji coba pemulihan (HALF-OPEN).
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-6 border-t border-gray-100 pt-3 sm:border-t-0 sm:border-l sm:pl-6 sm:pt-0 dark:border-gray-800">
                <div class="text-left sm:text-right">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Kegagalan Beruntun</p>
                    <p class="mt-0.5 text-xl font-bold {{ $circuitBreaker['failure_count'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-900 dark:text-white' }}">
                        {{ $circuitBreaker['failure_count'] }}
                    </p>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-xs font-medium uppercase tracking-wider text-gray-400 dark:text-gray-500">Kegagalan Terakhir</p>
                    <p class="mt-0.5 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        {{ $circuitBreaker['last_failure_at'] ?? 'Tidak ada failure' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Sync State Metrics Section --}}
    <x-filament::section heading="Metrik Sinkronisasi Pegawai">
        <x-slot name="description">
            Statistik dan rangkuman data sinkronisasi terakhir antara database SIMPEG dan Identity Provider.
        </x-slot>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {{-- Last Sync At --}}
            <div class="flex flex-col justify-between rounded-lg border border-gray-200 bg-gray-50/50 p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800/40 dark:hover:bg-gray-800/70">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Sync Terakhir</span>
                    <x-heroicon-m-calendar class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="mt-3">
                    <p class="text-base font-bold text-gray-900 dark:text-white">
                        {{ $syncState['last_sync_at'] ?? 'Belum pernah sync' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Timestamp eksekusi terakhir</p>
                </div>
            </div>

            {{-- Last Sync Type --}}
            <div class="flex flex-col justify-between rounded-lg border border-gray-200 bg-gray-50/50 p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800/40 dark:hover:bg-gray-800/70">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tipe Operasi</span>
                    <x-heroicon-m-tag class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="mt-3">
                    <p class="text-base font-bold text-gray-900 dark:text-white">
                        {{ $syncState['last_sync_type'] ? ucfirst($syncState['last_sync_type']) : '-' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Metode pembaruan data</p>
                </div>
            </div>

            {{-- Total Synced --}}
            <div class="flex flex-col justify-between rounded-lg border border-gray-200 bg-gray-50/50 p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800/40 dark:hover:bg-gray-800/70">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Disinkronkan</span>
                    <x-heroicon-m-check-badge class="h-4 w-4 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                        {{ number_format($syncState['total_synced']) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Data akun terverifikasi & terupdate</p>
                </div>
            </div>

            {{-- Total Conflicts --}}
            <div class="flex flex-col justify-between rounded-lg border border-gray-200 bg-gray-50/50 p-4 transition hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800/40 dark:hover:bg-gray-800/70">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Total Konflik</span>
                    <x-heroicon-m-exclamation-triangle class="h-4 w-4 {{ $syncState['total_conflicts'] > 0 ? 'text-amber-500' : 'text-gray-400' }}" />
                </div>
                <div class="mt-3">
                    <p class="text-2xl font-bold {{ $syncState['total_conflicts'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-white' }}">
                        {{ number_format($syncState['total_conflicts']) }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $syncState['total_conflicts'] > 0 ? 'Perlu evaluasi pada log audit' : 'Tidak ada anomali terdeteksi' }}
                    </p>
                </div>
            </div>
        </div>
    </x-filament::section>

    {{-- Panduan Aksi & Pengoperasian --}}
    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
            <x-heroicon-m-information-circle class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
            Panduan Operasional Tombol Aksi Header
        </h3>
        <div class="mt-4 grid grid-cols-1 gap-4 text-xs leading-relaxed text-gray-600 md:grid-cols-3 dark:text-gray-400">
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3.5 dark:border-gray-800/60 dark:bg-gray-800/30">
                <strong class="font-semibold text-gray-900 dark:text-white">1. Full Sync:</strong>
                <p class="mt-1">Menyinkronkan seluruh data pegawai aktif ke Keycloak IAM. Gunakan saat inisialisasi awal atau audit skala penuh.</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3.5 dark:border-gray-800/60 dark:bg-gray-800/30">
                <strong class="font-semibold text-gray-900 dark:text-white">2. Incremental Sync:</strong>
                <p class="mt-1">Menyinkronkan hanya data pegawai yang mengalami perubahan profil atau jabatan dalam 24 jam terakhir.</p>
            </div>
            <div class="rounded-lg border border-gray-100 bg-gray-50 p-3.5 dark:border-gray-800/60 dark:bg-gray-800/30">
                <strong class="font-semibold text-gray-900 dark:text-white">3. Sync by NIP & Reset:</strong>
                <p class="mt-1">Pembaruan instan untuk 1 pegawai via 18 digit NIP, atau me-reset circuit breaker jika koneksi IAM telah pulih.</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>

