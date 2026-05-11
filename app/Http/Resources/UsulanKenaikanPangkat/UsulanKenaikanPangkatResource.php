<?php

namespace App\Http\Resources\UsulanKenaikanPangkat;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource JSON untuk usulan kenaikan pangkat.
 */
class UsulanKenaikanPangkatResource extends JsonResource
{
    /**
     * Transform resource menjadi array API.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pegawai_id' => $this->pegawai_id,
            'pegawai' => $this->whenLoaded('pegawai'),
            'ref_pangkat_asal_id' => $this->ref_pangkat_asal_id,
            'pangkat_asal' => $this->whenLoaded('pangkatAsal'),
            'ref_pangkat_tujuan_id' => $this->ref_pangkat_tujuan_id,
            'pangkat_tujuan' => $this->whenLoaded('pangkatTujuan'),
            'tmt_pangkat_asal' => $this->tmt_pangkat_asal?->toDateString(),
            'periode_usul_bulan' => $this->periode_usul_bulan,
            'periode_usul_tahun' => $this->periode_usul_tahun,
            'nomor_usulan' => $this->nomor_usulan,
            'tanggal_usulan' => $this->tanggal_usulan?->toDateString(),
            'state' => (string) $this->state,
            'catatan_pengusul' => $this->catatan_pengusul,
            'catatan_penolakan' => $this->catatan_penolakan,
            'nomor_sk' => $this->nomor_sk,
            'tanggal_sk' => $this->tanggal_sk?->toDateString(),
            'sk_file_original_name' => $this->sk_file_original_name,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'finalized_at' => $this->finalized_at?->toISOString(),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'approval_steps' => $this->whenLoaded('approvalSteps'),
            'state_history' => $this->whenLoaded('stateHistory'),
            'approver_history' => $this->whenLoaded('approverHistory'),
            'lampiran' => $this->whenLoaded('lampiran'),
            'pdfs' => $this->whenLoaded('pdfs'),
            'checklist_submission' => $this->whenLoaded('checklistSubmission'),
        ];
    }
}
