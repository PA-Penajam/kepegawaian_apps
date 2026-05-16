<?php

namespace App\Services\Iam;

use App\Models\IamPermission;
use Illuminate\Support\Collection;

class IamPermissionAuditor
{
    /**
     * Temukan semua IamPermission yang slugnya tidak canonical.
     *
     * @return Collection<int, array{id:string, slug:string, app:string, reason:string, suggested:?string}>
     */
    public function findNonCanonical(): Collection
    {
        return IamPermission::with('application')
            ->get()
            ->filter(fn (IamPermission $p) => ! $this->isValidSlug($p->slug))
            ->map(fn (IamPermission $p) => [
                'id'        => $p->id,
                'slug'      => $p->slug,
                'app'       => $p->application->slug,
                'reason'    => $this->violationReason($p->slug),
                'suggested' => $this->suggestCanonical($p->slug),
            ])
            ->values();
    }

    /**
     * Periksa apakah slug sesuai dengan pola konvensi dari config.
     */
    public function isValidSlug(string $slug): bool
    {
        return (bool) preg_match(config('iam.slug.pattern'), $slug);
    }

    /**
     * Saran konservatif: hanya tangani slug single-segment dengan strip trailing
     * (mis. `iam-manage` → `iam.manage`). Kasus lain dikembalikan null
     * agar developer putuskan manual.
     */
    public function suggestCanonical(string $slug): ?string
    {
        // Jika sudah punya titik — tidak bisa disarankan otomatis
        if (str_contains($slug, '.')) {
            return null;
        }

        // Jika mengandung underscore — tidak bisa disarankan otomatis
        if (str_contains($slug, '_')) {
            return null;
        }

        // Harus punya strip untuk bisa dikonversi
        if (! str_contains($slug, '-')) {
            return null;
        }

        // Konversi strip TERAKHIR menjadi titik: iam-manage → iam.manage
        $pos = strrpos($slug, '-');
        $candidate = substr($slug, 0, $pos).'.'.substr($slug, $pos + 1);

        return $this->isValidSlug($candidate) ? $candidate : null;
    }

    /**
     * Berikan alasan spesifik mengapa slug dianggap melanggar konvensi.
     * Urutan pengecekan: tidak ada titik → uppercase → underscore → fallback.
     */
    private function violationReason(string $slug): string
    {
        if (! str_contains($slug, '.')) {
            return 'Tidak ada titik pemisah';
        }

        if (preg_match('/[A-Z]/', $slug)) {
            return 'Mengandung uppercase';
        }

        if (str_contains($slug, '_')) {
            return 'Underscore tidak diizinkan';
        }

        return 'Format tidak match regex konvensi';
    }
}
