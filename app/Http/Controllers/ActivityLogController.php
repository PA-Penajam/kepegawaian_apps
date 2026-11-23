<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    /** Namespace dasar model untuk konversi class_basename -> FQCN. */
    private const MODEL_NAMESPACE = 'App\\Models\\';

    public function index(Request $request): Response
    {
        $rawSubjectType = $request->input('subject_type');
        $subjectType = is_string($rawSubjectType) && trim($rawSubjectType) !== '' ? trim($rawSubjectType) : null;
        $rawCauserId = $request->input('causer_id');
        $causerId = is_string($rawCauserId) && trim($rawCauserId) !== '' ? trim($rawCauserId) : null;
        $rawDateFrom = $request->input('date_from');
        $dateFrom = is_string($rawDateFrom) && trim($rawDateFrom) !== '' ? trim($rawDateFrom) : null;
        $rawDateTo = $request->input('date_to');
        $dateTo = is_string($rawDateTo) && trim($rawDateTo) !== '' ? trim($rawDateTo) : null;

        $activities = Activity::with('causer', 'subject')
            ->when(
                $subjectType !== null && $subjectType !== 'all',
                fn ($q) => $q->where('subject_type', self::resolveModelFqcn($subjectType)),
            )
            ->when($causerId !== null, fn ($q) => $q->where('causer_id', $causerId))
            ->when($dateFrom !== null, fn ($q) => $q->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($q) => $q->whereDate('created_at', '<=', $dateTo))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Activity $activity): array => [
                'id' => $activity->id,
                'waktu' => $activity->created_at->format('d M Y H:i'),
                'oleh' => $activity->causer?->nama_lengkap ?? 'Sistem',
                'aksi' => $activity->description,
                'model' => class_basename($activity->subject_type ?? ''),
                'subject_id' => $activity->subject_id,
                'old' => $activity->attribute_changes?->get('old', []),
                'new' => $activity->attribute_changes?->get('attributes', []),
            ]);

        $subjectTypes = Activity::distinct()
            ->whereNotNull('subject_type')
            ->pluck('subject_type')
            ->map(fn ($type) => class_basename($type))
            ->values();

        return inertia('activity-log/index', compact('activities', 'subjectTypes'));
    }

    /**
     * Konversi class_basename (misal "Pegawai") menjadi FQCN ("App\Models\Pegawai").
     * Jika input sudah FQCN (ada backslash), kembalikan apa adanya.
     */
    private static function resolveModelFqcn(string $type): string
    {
        if (str_contains($type, '\\')) {
            return $type;
        }

        return self::MODEL_NAMESPACE.$type;
    }
}
