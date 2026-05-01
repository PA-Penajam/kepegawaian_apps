<?php

namespace App\Http\Controllers\Cuti;

use App\Http\Controllers\Controller;
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
            ->with('causer:id,nip,nama_lengkap')
            ->latest();

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->input('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->input('to'));
        }

        if ($request->filled('aktor')) {
            $search = $request->input('aktor');
            $query->whereHasMorph('causer', ['App\Models\Pegawai'], function ($q) use ($search) {
                $q->where('nip', 'like', "%{$search}%")
                    ->orWhere('nama_lengkap', 'like', "%{$search}%");
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
                    'nip' => $activity->causer->nip,
                    'nama' => $activity->causer->nama_lengkap,
                ] : null,
                'properties' => $activity->properties->toArray(),
                'created_at' => $activity->created_at->toIso8601String(),
            ];
        });

        return Inertia::render('cuti/admin/audit', [
            'activities' => $activities,
            'filters' => $request->only(['from', 'to', 'aktor']),
        ]);
    }
}
