<?php

namespace App\Jobs\SuratDinas;

use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\SuratDinas\SuratDinasRequestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RegisterSuratDinasJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [30, 120, 300, 600];

    public function __construct(
        public readonly string $suratDinasRequestId,
    ) {}

    public function handle(SuratDinasRequestService $service): void
    {
        $request = SuratDinasRequest::query()->find($this->suratDinasRequestId);
        if ($request === null) {
            return;
        }

        $service->registerToGateway($request);
    }
}
