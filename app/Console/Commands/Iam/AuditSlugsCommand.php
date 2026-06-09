<?php

namespace App\Console\Commands\Iam;

use App\Services\Iam\IamPermissionAuditor;
use Illuminate\Console\Command;

class AuditSlugsCommand extends Command
{
    protected $signature = 'iam:audit-slugs
                            {--app= : Filter ke aplikasi tertentu (slug)}
                            {--json : Output JSON untuk piping}';

    protected $description = 'Audit slug permission IAM yang melanggar konvensi';

    public function handle(IamPermissionAuditor $auditor): int
    {
        $items = $auditor->findNonCanonical();

        if ($appSlug = $this->option('app')) {
            $items = $items->filter(fn ($p) => $p['app'] === $appSlug)->values();
        }

        if ($this->option('json')) {
            // Output JSON konsisten untuk piping (jq) — empty array kalau bersih
            $this->line($items->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($items->isEmpty()) {
            $this->info('✅ Semua slug permission canonical. Tidak ada yang perlu di-migrate.');

            return self::SUCCESS;
        }

        $this->warn("Ditemukan {$items->count()} slug non-canonical:");
        $this->table(
            ['App', 'Slug Sekarang', 'Alasan', 'Saran Canonical'],
            $items->map(fn ($p) => [
                $p['app'], $p['slug'], $p['reason'], $p['suggested'] ?? '—',
            ])->toArray(),
        );

        $this->newLine();
        $this->line('Migrate via UI: halaman aplikasi IAM → tab Permissions → tombol [Migrate]');
        $this->line('Atau edit manual jika kasus kompleks.');

        return self::SUCCESS;
    }
}
