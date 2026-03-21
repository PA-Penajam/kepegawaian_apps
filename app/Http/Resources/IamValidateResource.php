<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class IamValidateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->nama_lengkap,
            'email' => $this->email,
            'nip'   => $this->nip,
        ];
    }
}
