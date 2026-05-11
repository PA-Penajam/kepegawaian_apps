<?php

namespace App\Http\Controllers\UsulanKenaikanPangkat;

use App\Http\Controllers\Controller;
use App\Http\Requests\UsulanKenaikanPangkat\BatalkanUsulanRequest;
use App\Http\Requests\UsulanKenaikanPangkat\StoreUsulanKenaikanPangkatRequest;
use App\Http\Requests\UsulanKenaikanPangkat\SubmitUsulanKenaikanPangkatRequest;
use App\Models\RefPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UsulanKenaikanPangkatController extends Controller
{
    public function __construct(
        private readonly UsulanKenaikanPangkatService $service,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', UsulanKenaikanPangkat::class);

        $filters = $request->only(['state', 'periode', 'pegawai']);
        $usulan = UsulanKenaikanPangkat::query()
            ->with(['pegawai:id,nip,nama_lengkap', 'pangkatTujuan:id,kode,nama,golongan,ruang'])
            ->when($filters['state'] ?? null, fn ($query, string $state) => $query->where('state', $state))
            ->when($filters['periode'] ?? null, function ($query, string $periode): void {
                [$tahun, $bulan] = array_pad(explode('-', $periode, 2), 2, null);

                $query->when($tahun, fn ($query, string $tahun) => $query->where('periode_usul_tahun', (int) $tahun))
                    ->when($bulan, fn ($query, string $bulan) => $query->where('periode_usul_bulan', (int) $bulan));
            })
            ->when($filters['pegawai'] ?? null, function ($query, string $pegawai): void {
                $query->whereHas('pegawai', fn ($query) => $query
                    ->where('nama_lengkap', 'like', "%{$pegawai}%")
                    ->orWhere('nip', 'like', "%{$pegawai}%"));
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('kenaikan-pangkat/usulan/index', [
            'usulan' => $usulan,
            'filters' => $filters,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', UsulanKenaikanPangkat::class);

        return Inertia::render('kenaikan-pangkat/usulan/form', [
            'usulan' => null,
            'defaultPegawaiId' => $request->query('pegawai_id'),
            'pangkatOptions' => RefPangkat::query()->orderBy('urutan')->get(['id', 'kode', 'nama', 'golongan', 'ruang']),
        ]);
    }

    public function store(StoreUsulanKenaikanPangkatRequest $request): RedirectResponse
    {
        $this->authorize('create', UsulanKenaikanPangkat::class);

        $usulan = $this->service->createDraft($request->validated(), $request->user());

        return redirect()->route('usulan-kenaikan-pangkat.show', $usulan);
    }

    public function show(UsulanKenaikanPangkat $usulan): Response
    {
        $this->authorize('view', $usulan);

        return Inertia::render('kenaikan-pangkat/usulan/show', [
            'usulan' => $usulan->load([
                'pegawai:id,nip,nama_lengkap',
                'pangkatAsal:id,kode,nama,golongan,ruang',
                'pangkatTujuan:id,kode,nama,golongan,ruang',
                'approvalSteps',
                'stateHistory',
                'approverHistory',
                'lampiran',
                'checklistSubmission.items',
            ]),
            'timeline' => $usulan->stateHistory()->latest()->get(),
            'lampiran' => $usulan->lampiran()->latest()->get(),
            'checklist' => $usulan->checklistSubmission()->with('items')->first(),
        ]);
    }

    public function edit(UsulanKenaikanPangkat $usulan): Response
    {
        $this->authorize('update', $usulan);

        return Inertia::render('kenaikan-pangkat/usulan/form', [
            'usulan' => $usulan->load(['pegawai:id,nip,nama_lengkap', 'pangkatTujuan:id,kode,nama,golongan,ruang']),
            'defaultPegawaiId' => $usulan->pegawai_id,
            'pangkatOptions' => RefPangkat::query()->orderBy('urutan')->get(['id', 'kode', 'nama', 'golongan', 'ruang']),
        ]);
    }

    public function update(StoreUsulanKenaikanPangkatRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('update', $usulan);

        $usulan->update($request->validated());

        return redirect()->route('usulan-kenaikan-pangkat.show', $usulan);
    }

    public function destroy(UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('delete', $usulan);

        $usulan->delete();

        return redirect()->route('usulan-kenaikan-pangkat.index');
    }

    public function submit(SubmitUsulanKenaikanPangkatRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('submit', $usulan);

        $checklist = $request->validated('checklist_submission_id')
            ? $usulan->checklistSubmission()->with(['template.items', 'items'])->find($request->validated('checklist_submission_id'))
            : null;

        $this->service->submit($usulan, $request->user(), $checklist);

        return redirect()->route('usulan-kenaikan-pangkat.show', $usulan);
    }

    public function batalkan(BatalkanUsulanRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('batalkan', $usulan);

        $this->service->batalkan($usulan, $request->user(), $request->validated('alasan'));

        return redirect()->route('usulan-kenaikan-pangkat.show', $usulan);
    }
}
