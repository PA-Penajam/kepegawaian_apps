<?php

namespace App\Http\Controllers\Cuti;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Support\SqlLike;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    /**
     * Menampilkan halaman audit log khusus modul cuti.
     */
    public function index(Request $request): Response
    {
        $query = Activity::query()
            ->where('log_name', 'cuti')
            ->with('causer')
            ->latest();

        $rawFrom = $request->input('from');
        $from = is_string($rawFrom) && trim($rawFrom) !== '' ? trim($rawFrom) : null;

        $rawTo = $request->input('to');
        $to = is_string($rawTo) && trim($rawTo) !== '' ? trim($rawTo) : null;

        $rawAktor = $request->input('aktor');
        $aktor = is_string($rawAktor) && trim($rawAktor) !== '' ? trim($rawAktor) : null;

        if ($from !== null) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($aktor !== null) {
            $query->whereHasMorph('causer', [Pegawai::class], function ($q) use ($aktor) {
                SqlLike::whereContains($q, ['nip', 'nama_lengkap'], $aktor);
            });
        }

        $activities = $query->paginate(20)->through(function (Activity $activity) {
            return [
                'id' => $activity->id,
                'log_name' => $activity->log_name,
                'description' => $activity->description,
                'subject_type' => class_basename($activity->subject_type ?? ''),
                'subject_id' => $activity->subject_id,
                'causer_type' => $activity->causer_type ? class_basename($activity->causer_type) : null,
                'causer_id' => $activity->causer_id,
                'causer' => $activity->causer ? [
                    'nip' => $activity->causer->nip ?? '',
                    'nama' => $activity->causer->nama_lengkap ?? $activity->causer->nama ?? '-',
                ] : null,
                'attribute_changes' => $activity->attribute_changes?->toArray() ?? [],
                'properties' => $activity->properties?->toArray() ?? [],
                'created_at' => $activity->created_at->toIso8601String(),
            ];
        });

        return Inertia::render('cuti/admin/audit', [
            'activities' => $activities,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'aktor' => $aktor,
            ],
        ]);
    }
}
