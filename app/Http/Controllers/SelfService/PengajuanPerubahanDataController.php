<?php

namespace App\Http\Controllers\SelfService;

use App\Enums\AksiPengajuan;
use App\Enums\DomainPengajuan;
use App\Enums\HubunganKeluarga;
use App\Enums\JenisKelamin;
use App\Enums\StatusPerkawinan;
use App\Http\Controllers\Controller;
use App\Http\Requests\SelfService\StorePengajuanPerubahanDataRequest;
use App\Models\PengajuanPerubahanData;
use App\Services\PengajuanPerubahanData\PengajuanPerubahanDataDiffService;
use App\Services\PengajuanPerubahanData\SubmitPengajuanPerubahanDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PengajuanPerubahanDataController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('self-service/pengajuan/index', [
            'pengajuanList' => PengajuanPerubahanData::query()
                ->where('pengaju_id', $request->user()->id)
                ->with('validator')
                ->latest('submitted_at')
                ->paginate(10)
                ->through(fn (PengajuanPerubahanData $item) => [
                    'id' => $item->id,
                    'domain' => $item->domain,
                    'aksi' => $item->aksi,
                    'status' => $item->status->value,
                    'submitted_at' => $item->submitted_at?->toDateTimeString(),
                    'approved_at' => $item->approved_at?->toDateTimeString(),
                    'rejected_at' => $item->rejected_at?->toDateTimeString(),
                    'validator' => $item->validator?->nama_lengkap,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('self-service/pengajuan/create', [
            'domains' => array_map(
                fn (DomainPengajuan $d) => ['value' => $d->value, 'label' => str_replace('_', ' ', $d->name)],
                DomainPengajuan::cases()
            ),
            'aksiList' => array_map(
                fn (AksiPengajuan $a) => ['value' => $a->value, 'label' => ucfirst($a->value)],
                AksiPengajuan::cases()
            ),
            'hubunganList' => array_map(
                fn (HubunganKeluarga $h) => ['value' => $h->value, 'label' => $h->value],
                HubunganKeluarga::cases()
            ),
            'jenisKelaminList' => array_map(
                fn (JenisKelamin $j) => ['value' => $j->value, 'label' => $j === JenisKelamin::LakiLaki ? 'Laki-laki' : 'Perempuan'],
                JenisKelamin::cases()
            ),
            'statusPerkawinanList' => array_map(
                fn (StatusPerkawinan $s) => ['value' => $s->value, 'label' => str_replace('_', ' ', $s->name)],
                StatusPerkawinan::cases()
            ),
            'currentUserId' => auth()->id(),
        ]);
    }

    public function store(StorePengajuanPerubahanDataRequest $request, SubmitPengajuanPerubahanDataService $service): RedirectResponse
    {
        $service->handle($request->user(), $request->validated(), $request->user()->isOperator() ? 'operator' : 'pegawai');

        return to_route('self-service.pengajuan.index')
            ->with('success', 'Pengajuan berhasil dikirim dan menunggu validasi.');
    }

    public function show(PengajuanPerubahanData $pengajuan, PengajuanPerubahanDataDiffService $diffService): Response
    {
        abort_unless($pengajuan->pengaju_id === request()->user()->id, 404);

        return Inertia::render('self-service/pengajuan/show', [
            'pengajuan' => $pengajuan->load(['validator']),
            'diffItems' => $diffService->make($pengajuan->before_payload ?? [], $pengajuan->after_payload),
        ]);
    }
}
