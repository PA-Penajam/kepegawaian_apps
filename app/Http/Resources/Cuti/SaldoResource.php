<?php

namespace App\Http\Resources\Cuti;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk transformasi ringkasan saldo cuti per jenis per tahun.
 */
class SaldoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'jenis_cuti_kode' => $this['jenis_cuti_kode'],
            'tahun_hak' => $this['tahun_hak'],
            'hak_awal' => $this['hak_awal'],
            'saldo_tersedia' => $this['saldo_tersedia'],
        ];
    }
}
