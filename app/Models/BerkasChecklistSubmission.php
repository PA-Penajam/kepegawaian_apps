<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BerkasChecklistSubmission extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'berkas_checklist_submissions';

    protected $fillable = [
        'berkas_checklist_template_id',
        'subject_type',
        'subject_id',
        'pegawai_id',
        'status_kelengkapan',
        'persentase',
    ];

    protected function casts(): array
    {
        return [
            'persentase' => 'integer',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(BerkasChecklistTemplate::class, 'berkas_checklist_template_id');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
