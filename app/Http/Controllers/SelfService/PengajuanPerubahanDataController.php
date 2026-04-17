<?php

namespace App\Http\Controllers\SelfService;

use App\Http\Controllers\Controller;
use App\Http\Requests\SelfService\StorePengajuanPerubahanDataRequest;
use App\Models\PengajuanPerubahanData;
use App\Services\PengajuanPerubahanData\SubmitPengajuanPerubahanDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                    'id'           => $item->id,
                    'domain'       => $item->domain,
                    'aksi'         => $item->aksi,
                    'status'       => $item->status->value,
                    'submitted_at' => $item->submitted_at?->toDateTimeString(),
                    'approved_at'  => $item->approved_at?->toDateTimeString(),
                    'rejected_at'  => $item->rejected_at?->toDateTimeString(),
                    'validator'    => $item->validator?->nama_lengkap,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('self-service/pengajuan/create');
    }

    public function store(StorePengajuanPerubahanDataRequest $request, SubmitPengajuanPerubahanDataService $service): RedirectResponse
    {
        $service->handle($request->user(), $request->validated(), $request->user()->isOperator() ? 'operator' : 'pegawai');

        return to_route('self-service.pengajuan.index');
    }
}
