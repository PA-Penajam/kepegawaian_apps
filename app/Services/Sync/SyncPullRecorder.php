<?php

namespace App\Services\Sync;

use App\Models\PegawaiSyncPull;
use App\Models\SyncConsumer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Pencatat tarikan (pull) data pegawai oleh konsumen sinkronisasi.
 *
 * Dipanggil dari PegawaiSyncController setelah respons dibangun. Konsumen
 * diidentifikasi dari token Sanctum pemanggil — baik token yang diterbitkan
 * lewat modul sinkronisasi (name "sync:{slug}") maupun token SSO legacy
 * (name "sso:{slug}") sehingga riwayat tetap terisi tanpa mengubah kontrak API.
 */
class SyncPullRecorder
{
    /**
     * Catat satu pull dan perbarui status kesehatan konsumen.
     */
    public function record(
        Request $request,
        int $rows,
        int $page,
        int $perPage,
        int $durationMs,
    ): void {
        $consumer = $this->resolveConsumer($request);

        // Ambil metadata token SEBELUM update model — update() me-refresh model
        // dan dapat menghapus relasi currentAccessToken pada instance yang sama.
        $tokenName = $this->tokenName($request);
        $clientAgent = $this->clientAgent($request);

        try {
            if ($consumer) {
                $consumer->update([
                    'last_pull_at' => now(),
                    'last_pull_status' => 'success',
                    'last_pull_rows' => $rows,
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Gagal memperbarui status pull konsumen', [
                'consumer' => $consumer?->slug,
                'exception' => $e->getMessage(),
            ]);
        }

        try {
            PegawaiSyncPull::create([
                'sync_consumer_id' => $consumer?->id,
                'status' => 'success',
                'rows_returned' => $rows,
                'page' => $page,
                'per_page' => $perPage,
                'duration_ms' => $durationMs,
                'token_name' => $tokenName,
                'client_agent' => $clientAgent,
                'pulled_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat pull pegawai', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolusi konsumen dari token pemanggil.
     */
    private function resolveConsumer(Request $request): ?SyncConsumer
    {
        // Jalur 1: pengguna terautentikasi adalah SyncConsumer (token "sync:*" atau "sso:*")
        $user = $request->user();

        if ($user instanceof SyncConsumer) {
            return $user;
        }

        // Jalur 2: cocokkan nama token dengan slug konsumen terdaftar
        $tokenName = $this->tokenName($request);

        if ($tokenName === null) {
            return null;
        }

        // Nama token: "sync:slug" atau "sso:slug"
        $parts = explode(':', $tokenName, 2);

        if (count($parts) !== 2) {
            return null;
        }

        return SyncConsumer::query()->where('slug', $parts[1])->first();
    }

    private function tokenName(Request $request): ?string
    {
        // Prioritas 1: token ter-resolve pada guard (paling akurat)
        try {
            $tokenName = $request->user()?->currentAccessToken()?->name;

            if ($tokenName !== null && $tokenName !== '') {
                return $tokenName;
            }
        } catch (\Throwable) {
            // Lanjut ke prioritas 2
        }

        // Prioritas 2: temukan token dari header Authorization.
        // Relasi token bisa hilang pada instance user hasil resolve, jadi
        // fallback ini memastikan nama token tetap tercatat.
        try {
            $authHeader = $request->header('Authorization');

            if (is_string($authHeader) && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
                $token = PersonalAccessToken::findToken($matches[1]);

                if ($token !== null) {
                    return $token->name;
                }
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function clientAgent(Request $request): ?string
    {
        $agent = $request->userAgent();

        return $agent !== null && $agent !== ''
            ? mb_substr($agent, 0, 255)
            : null;
    }
}
