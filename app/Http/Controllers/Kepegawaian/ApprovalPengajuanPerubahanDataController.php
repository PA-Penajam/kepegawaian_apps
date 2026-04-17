<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\ApprovePengajuanPerubahanDataRequest;
use App\Http\Requests\Kepegawaian\RejectPengajuanPerubahanDataRequest;
use App\Models\PengajuanPerubahanData;
use App\Services\PengajuanPerubahanData\ApprovePengajuanPerubahanDataService;
use App\Services\PengajuanPerubahanData\PengajuanPerubahanDataDiffService;
use App\Services\PengajuanPerubahanData\RejectPengajuanPerubahanDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalPengajuanPerubahanDataController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('kepegawaian/pengajuan/index', [
            'pengajuanList' => PengajuanPerubahanData::query()
                ->where('status', \App\Enums\StatusPengajuanPerubahanData::Pending)
                ->latest('submitted_at')
                ->paginate(10),
        ]);
    }

    public function show(PengajuanPerubahanData $pengajuan, PengajuanPerubahanDataDiffService $diffService): Response
    {
        return Inertia::render('kepegawaian/pengajuan/show', [
            'pengajuan' => $pengajuan->load(['pengaju', 'validator']),
            'diffItems' => $diffService->make($pengajuan->before_payload ?? [], $pengajuan->after_payload),
        ]);
    }

    public function approve(ApprovePengajuanPerubahanDataRequest $request, PengajuanPerubahanData $pengajuan, ApprovePengajuanPerubahanDataService $service): RedirectResponse
    {
        $service->handle($pengajuan, $request->user());

        return to_route('kepegawaian.pengajuan.show', $pengajuan);
    }

    public function reject(RejectPengajuanPerubahanDataRequest $request, PengajuanPerubahanData $pengajuan, RejectPengajuanPerubahanDataService $service): RedirectResponse
    {
        $service->handle($pengajuan, $request->user(), $request->validated('alasan_penolakan'));

        return to_route('kepegawaian.pengajuan.show', $pengajuan);
    }
}
