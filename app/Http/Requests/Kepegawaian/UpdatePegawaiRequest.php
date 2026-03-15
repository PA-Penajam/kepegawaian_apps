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

    public function rules(): array
    {
        $pegawai = $this->route('pegawai');

        return $this->pegawaiRules($pegawai instanceof Pegawai ? $pegawai : null);
    }
}
