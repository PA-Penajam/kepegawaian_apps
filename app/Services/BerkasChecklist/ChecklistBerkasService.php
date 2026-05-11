<?php

namespace App\Services\BerkasChecklist;

use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

class ChecklistBerkasService
{
    public function createSubmission(BerkasChecklistTemplate $template, Model $subject, Pegawai $pegawai): BerkasChecklistSubmission
    {
        return DB::transaction(function () use ($template, $subject, $pegawai): BerkasChecklistSubmission {
            $submission = BerkasChecklistSubmission::query()->create([
                'berkas_checklist_template_id' => $template->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
                'pegawai_id' => $pegawai->id,
                'status_kelengkapan' => 'belum_lengkap',
                'persentase' => 0,
            ]);

            $template->items()->ordered()->get()->each(function ($templateItem) use ($submission): void {
                $submission->items()->create([
                    'berkas_checklist_item_id' => $templateItem->id,
                    'status' => BerkasChecklistSubmissionItem::STATUS_BELUM_ADA,
                ]);
            });

            return $submission->load('items');
        });
    }

    public function updateItemStatus(BerkasChecklistSubmissionItem $item, string $status, ?string $catatan = null): void
    {
        $this->ensureValidStatus($status);

        $item->update([
            'status' => $status,
            'catatan' => $catatan,
        ]);
    }

    public function uploadFile(BerkasChecklistSubmissionItem $item, UploadedFile $file): void
    {
        $path = Storage::disk('local')->putFile("berkas-checklist/{$item->berkas_checklist_submission_id}", $file);

        $item->update([
            'status' => BerkasChecklistSubmissionItem::STATUS_ADA,
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);
    }

    public function validateItem(BerkasChecklistSubmissionItem $item, Pegawai $validator, string $newStatus, ?string $catatan = null): void
    {
        $this->ensureValidStatus($newStatus);

        $item->update([
            'status' => $newStatus,
            'catatan' => $catatan,
            'validated_by' => $validator->id,
            'validated_at' => now(),
        ]);
    }

    public function recalculatePersentase(BerkasChecklistSubmission $submission): void
    {
        $requiredItemIds = $submission->template->items()->wajib()->pluck('id');
        $requiredCount = $requiredItemIds->count();

        $validCount = $submission->items()
            ->whereIn('berkas_checklist_item_id', $requiredItemIds)
            ->where('status', BerkasChecklistSubmissionItem::STATUS_VALID)
            ->count();

        $percentage = $requiredCount === 0 ? 100 : (int) round(($validCount / $requiredCount) * 100);

        $submission->update([
            'persentase' => $percentage,
            'status_kelengkapan' => $percentage === 100 ? 'lengkap' : 'belum_lengkap',
        ]);
    }

    public function isComplete(BerkasChecklistSubmission $submission): bool
    {
        $requiredItemIds = $submission->template->items()->wajib()->pluck('id');

        if ($requiredItemIds->isEmpty()) {
            return true;
        }

        return ! $submission->items()
            ->whereIn('berkas_checklist_item_id', $requiredItemIds)
            ->where('status', '!=', BerkasChecklistSubmissionItem::STATUS_VALID)
            ->exists();
    }

    private function ensureValidStatus(string $status): void
    {
        if (! in_array($status, BerkasChecklistSubmissionItem::STATUSES, true)) {
            throw new InvalidArgumentException("Status {$status} tidak valid.");
        }
    }
}
