<?php

namespace Database\Factories\BerkasChecklist;

use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\DokumenPegawai;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BerkasChecklistSubmission>
 */
class BerkasChecklistSubmissionFactory extends Factory
{
    protected $model = BerkasChecklistSubmission::class;

    public function definition(): array
    {
        $subject = DokumenPegawai::factory()->create();

        return [
            'berkas_checklist_template_id' => BerkasChecklistTemplate::factory(),
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'pegawai_id' => Pegawai::factory(),
            'status_kelengkapan' => 'belum_lengkap',
            'persentase' => 0,
        ];
    }
}
