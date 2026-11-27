<?php

namespace App\Console\Commands;

use App\Services\Sisukma\Exceptions\SisukmaException;
use App\Services\Sisukma\SisukmaClient;
use Illuminate\Console\Command;

class SisukmaPingCommand extends Command
{
    protected $signature = 'sisukma:ping';

    protected $description = 'Menguji konektivitas dan tanda tangan HMAC ke API SiSukma';

    /**
     * Memanggil endpoint ping SiSukma dan menampilkan hasil verifikasi.
     */
    public function handle(SisukmaClient $client): int
    {
        try {
            $response = $client->ping();
        } catch (SisukmaException $e) {
            $this->error('Gagal terhubung ke SiSukma: '.$e->getMessage());

            return self::FAILURE;
        }

        $app = (string) ($response['app'] ?? 'sisukma');
        $clientSlug = (string) ($response['client'] ?? config('sisukma.client_id'));
        $this->info("Koneksi ke SiSukma berhasil (app: {$app}, client: {$clientSlug}).");

        return self::SUCCESS;
    }
}
