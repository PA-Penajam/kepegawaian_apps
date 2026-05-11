<?php

declare(strict_types=1);

namespace App\Services\Sikep;

use Illuminate\Support\Facades\Log;

readonly class NullSikepAdapter implements SikepAdapter
{
    public function pushUsulan(?object $usulan): ?array
    {
        Log::info('NullSikepAdapter::pushUsulan called', [
            'usulan' => $usulan ? get_class($usulan) : null,
        ]);

        return null;
    }

    public function pullStatusUsulan(string $externalId): ?string
    {
        Log::info('NullSikepAdapter::pullStatusUsulan called', [
            'external_id' => $externalId,
        ]);

        return null;
    }

    public function pullSkFinal(string $externalId): ?array
    {
        Log::info('NullSikepAdapter::pullSkFinal called', [
            'external_id' => $externalId,
        ]);

        return null;
    }

    public function isConfigured(): bool
    {
        Log::info('NullSikepAdapter::isConfigured called');

        return false;
    }
}
