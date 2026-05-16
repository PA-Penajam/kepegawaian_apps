<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidIamSlug implements ValidationRule
{
    // Tandai sebagai implicit agar slug kosong/null tetap divalidasi, bukan dilewati
    public bool $implicit = true;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pattern = config('iam.slug.pattern');

        if (! is_string($value) || ! preg_match($pattern, $value)) {
            $fail('Slug harus format {resource}.{action} atau {module}.{resource}.{action}. '
                . 'Contoh: pegawai.view, cuti.pengajuan.create. '
                . 'Lowercase, antar-segment pakai titik, antar-kata pakai strip.');

            return;
        }

        $segments = explode('.', $value);
        $max = (int) config('iam.slug.max_segments');
        if (count($segments) > $max) {
            $fail("Slug maksimal {$max} segment ({resource}.{action} atau {module}.{resource}.{action}).");
        }
    }
}
