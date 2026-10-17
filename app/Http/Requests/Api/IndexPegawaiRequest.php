<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi parameter pencarian/batch lookup pegawai.
 *
 * Mode batch (nip[]) divalidasi ketat 18 digit, sedangkan mode search
 * tidak menambahkan aturan baru agar kontrak client tetap sama.
 */
class IndexPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        if (! $this->has('nip')) {
            return [];
        }

        return [
            'nip' => ['required', 'array', 'max:50'],
            'nip.*' => ['required', 'string', 'digits:18'],
        ];
    }
}
