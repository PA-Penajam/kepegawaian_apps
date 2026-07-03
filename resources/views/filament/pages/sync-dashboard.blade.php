<x-filament-panels::page>
    @php
        $syncState = $this->getSyncStateData();
        $circuitBreaker = $this->getCircuitBreakerData();
    @endphp

    {{-- Sync State Section --}}
    <x-filament::section heading="Status Sinkronisasi">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            {{-- Last Sync At --}}
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sync Terakhir</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $syncState['last_sync_at'] ?? 'Belum pernah sync' }}
                </p>
            </div>

            {{-- Last Sync Type --}}
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tipe Sync Terakhir</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $syncState['last_sync_type'] ? ucfirst($syncState['last_sync_type']) : '-' }}
                </p>
            </div>

            {{-- Total Synced --}}
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Disinkronkan</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ number_format($syncState['total_synced']) }}
                </p>
            </div>

            {{-- Total Conflicts --}}
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Konflik</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ number_format($syncState['total_conflicts']) }}
                </p>
            </div>
        </div>
    </x-filament::section>

    {{-- Circuit Breaker Section --}}
    <x-filament::section heading="Circuit Breaker">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            {{-- State --}}
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">State</p>
                <div class="mt-1 flex items-center gap-2">
                    <x-filament::badge :color="$circuitBreaker['state_color']">
                        {{ $circuitBreaker['state'] }}
                    </x-filament::badge>
                </div>
            </div>

            {{-- Failure Count --}}
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Failure Count</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $circuitBreaker['failure_count'] }}
                </p>
            </div>

            {{-- Last Failure At --}}
            <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Failure Terakhir</p>
                <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                    {{ $circuitBreaker['last_failure_at'] ?? 'Tidak ada failure' }}
                </p>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
