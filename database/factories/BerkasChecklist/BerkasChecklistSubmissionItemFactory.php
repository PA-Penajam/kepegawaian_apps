<?php

namespace Database\Factories\BerkasChecklist;

use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BerkasChecklistSubmissionItem>
 */
class BerkasChecklistSubmissionItemFactory extends Factory
{
    protected $model = BerkasChecklistSubmissionItem::class;

    public function definition(): array
    {
        return [
            'berkas_checklist_submission_id' => BerkasChecklistSubmission::factory(),
            'berkas_checklist_item_id' => BerkasChecklistItem::factory(),
            'status' => BerkasChecklistSubmissionItem::STATUS_BELUM_ADA,
            'catatan' => null,
            'file_path' => null,
            'file_original_name' => null,
            'file_mime' => null,
            'file_size' => null,
            'validated_by' => null,
            'validated_at' => null,
        ];
    }
}
