<?php

namespace App\Http\Requests\Kepegawaian;

use App\Models\Pegawai;

class UpdatePegawaiRequest extends StorePegawaiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $pegawai = $this->route('pegawai');

        return $pegawai instanceof Pegawai
            && ($this->user()?->can('update', $pegawai) ?? false);
    }

    /**
     * Aturan validasi pembaruan pegawai; mutasi password lokal diabaikan pada mode SSO-only.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pegawai = $this->route('pegawai');

        return $this->pegawaiRules($pegawai instanceof Pegawai ? $pegawai : null);
    }
}
