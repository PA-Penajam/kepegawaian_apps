<?php

namespace App\Http\Controllers\Iam;

use App\Http\Controllers\Controller;
use App\Models\IamApplication;
use App\Services\Iam\IamSecretService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IamSecretRecoveryController extends Controller
{
    public function show(
        Request $request,
        IamApplication $aplikasi,
        IamSecretService $secretService,
    ): RedirectResponse {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat di-recover');

        $plaintext = $secretService->recoverFromCache($aplikasi, $request);

        if ($plaintext === null) {
            return back()->with(
                'error',
                'Secret sudah tidak bisa dipulihkan. Silakan regenerasi key untuk membuat secret baru.',
            );
        }

        return back()->with('api_secret_once', $plaintext);
    }

    public function acknowledge(
        Request $request,
        IamApplication $aplikasi,
        IamSecretService $secretService,
    ): RedirectResponse {
        abort_if($aplikasi->is_system, 403, 'Aplikasi sistem tidak dapat di-acknowledge');

        $secretService->invalidateRecovery($aplikasi, $request);

        return back()->with('success', 'Cache recovery secret telah dihapus.');
    }
}
