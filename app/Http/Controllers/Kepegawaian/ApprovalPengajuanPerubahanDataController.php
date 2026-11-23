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
use Illuminate\Validation\ValidationException;
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
                ->withQueryString()
                ->through(fn (PengajuanPerubahanData $item): array => [
                    'id' => $item->id,
                    'nomor_pengajuan' => $item->nomor_pengajuan,
                    'domain' => $item->domain,
                    'aksi' => $item->aksi,
                    'status' => $item->status instanceof StatusPengajuanPerubahanData
                        ? $item->status->value
                        : (string) $item->status,
                    'submitted_at' => $item->submitted_at?->toDateTimeString(),
                    'pengaju' => $item->pengaju ? [
                        'id' => $item->pengaju->id,
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
        Gate::authorize('approve', $pengajuan);

        try {
            $service->handle($pengajuan, $request->user());
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([
                'pengajuan' => $e->getMessage(),
            ]);
        }

        return to_route('kepegawaian.pengajuan.show', $pengajuan)
            ->with('success', 'Pengajuan telah disetujui.');
    }

    public function reject(RejectPengajuanPerubahanDataRequest $request, PengajuanPerubahanData $pengajuan, RejectPengajuanPerubahanDataService $service): RedirectResponse
    {
        Gate::authorize('reject', $pengajuan);

        try {
            $service->handle($pengajuan, $request->user(), $request->validated('alasan_penolakan'));
        } catch (\DomainException $e) {
            throw ValidationException::withMessages([
                'pengajuan' => $e->getMessage(),
            ]);
        }

        return to_route('kepegawaian.pengajuan.show', $pengajuan)
            ->with('success', 'Pengajuan telah ditolak.');
    }
}
