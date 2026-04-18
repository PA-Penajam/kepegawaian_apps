<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Enums\StatusPengajuanPerubahanData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\ApprovePengajuanPerubahanDataRequest;
use App\Http\Requests\Kepegawaian\RejectPengajuanPerubahanDataRequest;
use App\Models\PengajuanPerubahanData;
use App\Services\PengajuanPerubahanData\ApprovePengajuanPerubahanDataService;
use App\Services\PengajuanPerubahanData\PengajuanPerubahanDataDiffService;
use App\Services\PengajuanPerubahanData\RejectPengajuanPerubahanDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalPengajuanPerubahanDataController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', PengajuanPerubahanData::class);

        return Inertia::render('kepegawaian/pengajuan/index', [
            'pengajuanList' => PengajuanPerubahanData::query()
                ->with('pengaju:id,nama_lengkap,nip')
                ->where('status', StatusPengajuanPerubahanData::Pending)
                ->latest('submitted_at')
                ->paginate(10)
                ->through(fn (PengajuanPerubahanData $item) => [
                    'id' => $item->id,
                    'domain' => $item->domain,
                    'aksi' => $item->aksi,
                    'submitted_at' => $item->submitted_at?->toDateTimeString(),
                    'pengaju' => $item->pengaju ? [
                        'nama_lengkap' => $item->pengaju->nama_lengkap,
                        'nip' => $item->pengaju->nip,
                    ] : null,
                ]),
        ]);
    }

    public function show(PengajuanPerubahanData $pengajuan, PengajuanPerubahanDataDiffService $diffService): Response
    {
        Gate::authorize('viewAny', PengajuanPerubahanData::class);

        return Inertia::render('kepegawaian/pengajuan/show', [
            'pengajuan' => $pengajuan->load(['pengaju', 'validator']),
            'diffItems' => $diffService->make($pengajuan->before_payload ?? [], $pengajuan->after_payload),
        ]);
    }

    public function approve(ApprovePengajuanPerubahanDataRequest $request, PengajuanPerubahanData $pengajuan, ApprovePengajuanPerubahanDataService $service): RedirectResponse
    {
        $service->handle($pengajuan, $request->user());

        return to_route('kepegawaian.pengajuan.show', $pengajuan)
            ->with('success', 'Pengajuan telah disetujui.');
    }

    public function reject(RejectPengajuanPerubahanDataRequest $request, PengajuanPerubahanData $pengajuan, RejectPengajuanPerubahanDataService $service): RedirectResponse
    {
        $service->handle($pengajuan, $request->user(), $request->validated('alasan_penolakan'));

        return to_route('kepegawaian.pengajuan.show', $pengajuan)
            ->with('success', 'Pengajuan telah ditolak.');
    }
}
