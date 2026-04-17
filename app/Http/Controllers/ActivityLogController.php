<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $activities = Activity::with('causer', 'subject')
            ->when($request->input('subject_type'), fn ($q, $type) => $q->where('subject_type', $type))
            ->when($request->input('causer_id'), fn ($q, $id) => $q->where('causer_id', $id))
            ->when($request->input('date_from'), fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($request->input('date_to'), fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->latest()
            ->paginate(20)
            ->through(fn (Activity $activity): array => [
                'id'           => $activity->id,
                'waktu'        => $activity->created_at->format('d M Y H:i'),
                'oleh'         => $activity->causer?->nama_lengkap ?? 'Sistem',
                'aksi'         => $activity->description,
                'model'        => class_basename($activity->subject_type ?? ''),
                'subject_id'   => $activity->subject_id,
                'old'          => $activity->attribute_changes->get('old', []),
                'new'          => $activity->attribute_changes->get('attributes', []),
            ]);

        $subjectTypes = Activity::distinct()
            ->whereNotNull('subject_type')
            ->pluck('subject_type')
            ->map(fn ($type) => class_basename($type))
            ->values();

        return inertia('activity-log/index', compact('activities', 'subjectTypes'));
    }
}
