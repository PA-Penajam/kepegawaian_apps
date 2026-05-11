<?php

namespace App\Events;

use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

readonly class ChecklistKelengkapanBerubah
{
    use Dispatchable, SerializesModels;

    public function __construct(public BerkasChecklistSubmission $submission) {}
}
