<?php

namespace App\Http\Controllers\UsulanKenaikanPangkat;

use App\Exceptions\UsulanKenaikanPangkat\BerkasBelumLengkapException;
use App\Exceptions\UsulanKenaikanPangkat\DuplicateUsulanException;
use App\Exceptions\UsulanKenaikanPangkat\PegawaiTidakEligibleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UsulanKenaikanPangkat\BatalkanUsulanRequest;
use App\Http\Requests\UsulanKenaikanPangkat\StoreLampiranUsulanRequest;
use App\Http\Requests\UsulanKenaikanPangkat\StoreUsulanKenaikanPangkatRequest;
use App\Http\Requests\UsulanKenaikanPangkat\SubmitUsulanKenaikanPangkatRequest;
use App\Http\Requests\UsulanKenaikanPangkat\UpdateUsulanKenaikanPangkatRequest;
use App\Http\Requests\UsulanKenaikanPangkat\UploadChecklistItemRequest;
use App\Http\Requests\UsulanKenaikanPangkat\ValidateChecklistItemRequest;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpLampiran;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\KenaikanPangkatMonitoringService;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use App\Support\SqlLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

class UsulanKenaikanPangkatController extends Controller
{
    public function __construct(
        private readonly UsulanKenaikanPangkatService $service,
        private readonly ChecklistBerkasService $checklistBerkasService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', UsulanKenaikanPangkat::class);

        $searchRaw = $request->input('search') ?? $request->input('pegawai');
        $search = is_string($searchRaw) && trim($searchRaw) !== '' ? trim($searchRaw) : null;

        $stateInput = $request->input('state');
        $rawStates = is_array($stateInput)
            ? $stateInput
            : (is_string($stateInput) ? explode(',', $stateInput) : []);
        $states = collect($rawStates)
            ->filter(fn (mixed $item): bool => is_scalar($item) && ! is_bool($item))
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        $bulan = $request->filled('bulan') ? $request->integer('bulan') : null;
        $tahun = $request->filled('tahun') ? $request->integer('tahun') : null;

        $rawPeriode = $request->input('periode');
        if ($bulan === null && $tahun === null && is_string($rawPeriode) && trim($rawPeriode) !== '') {
            [$periodeTahun, $periodeBulan] = array_pad(explode('-', $rawPeriode, 2), 2, null);
            $tahun = filled($periodeTahun) ? (int) $periodeTahun : null;
            $bulan = filled($periodeBulan) ? (int) $periodeBulan : null;
        }

        $usulanList = UsulanKenaikanPangkat::query()
            ->with([
                'pegawai:id,nip,nama_lengkap',
                'pangkatAsal:id,kode,nama,golongan,ruang',
                'pangkatTujuan:id,kode,nama,golongan,ruang',
                'checklistSubmission.template.items',
                'checklistSubmission.items',
            ])
            ->when($states !== [], fn ($query) => $query->whereIn('state', $states))
            ->when($tahun !== null, fn ($query) => $query->where('periode_usul_tahun', $tahun))
            ->when($bulan !== null, fn ($query) => $query->where('periode_usul_bulan', $bulan))
            ->when($search !== null, function ($query) use ($search): void {
                $query->whereHas('pegawai', function ($pegawaiQuery) use ($search): void {
                    SqlLike::whereContains($pegawaiQuery, ['nama_lengkap', 'nip'], $search);
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(function (UsulanKenaikanPangkat $item): array {
                $submission = $item->checklistSubmission;
                $requiredIds = $submission?->template?->items
                    ?->where('wajib', true)
                    ->pluck('id') ?? collect();
                $totalChecklist = $requiredIds->count();
                $completedChecklist = $totalChecklist > 0 && $submission !== null
                    ? $submission->items
                        ->whereIn('berkas_checklist_item_id', $requiredIds)
                        ->where('status', BerkasChecklistSubmissionItem::STATUS_VALID)
                        ->pluck('berkas_checklist_item_id')
                        ->unique()
                        ->count()
                    : 0;

                return [
                    'id' => $item->id,
                    'nomor_usulan' => $item->nomor_usulan,
                    'pegawai' => $item->pegawai ? [
                        'id' => $item->pegawai->id,
                        'nip' => $item->pegawai->nip,
                        'nama_lengkap' => $item->pegawai->nama_lengkap,
                    ] : [
                        'nip' => null,
                        'nama_lengkap' => '-',
                    ],
                    'pangkat_asal' => $item->pangkatAsal?->only(['id', 'kode', 'nama', 'golongan', 'ruang']),
                    'pangkat_tujuan' => $item->pangkatTujuan?->only(['id', 'kode', 'nama', 'golongan', 'ruang']),
                    'periode_usul_bulan' => $item->periode_usul_bulan,
                    'periode_usul_tahun' => $item->periode_usul_tahun,
                    'periode_bulan' => $item->periode_usul_bulan,
                    'periode_tahun' => $item->periode_usul_tahun,
                    'state' => (string) $item->state,
                    'checklist_progress' => $submission ? [
                        'completed' => $completedChecklist,
                        'total' => $totalChecklist,
                        'percentage' => (int) ($submission->persentase ?? 0),
                    ] : null,
                ];
            });

        return Inertia::render('kenaikan-pangkat/usulan/index', [
            'usulanList' => $usulanList,
            'filters' => [
                'state' => $stateInput,
                'bulan' => $bulan,
                'tahun' => $tahun,
                'search' => $search,
            ],
            'stateLabels' => $this->stateLabels(),
            'bulanOptions' => $this->bulanOptions(),
            'tahunOptions' => $this->tahunOptions(),
        ]);
    }

    public function eligible(Request $request, KenaikanPangkatMonitoringService $monitoringService): Response
    {
        $this->authorize('viewAny', UsulanKenaikanPangkat::class);

        $bulan = $request->filled('bulan') ? $request->integer('bulan') : null;
        $tahun = $request->filled('tahun') ? $request->integer('tahun') : null;
        $rawUnitKerja = $request->input('unit_kerja');
        $unitKerja = is_string($rawUnitKerja) && trim($rawUnitKerja) !== '' ? trim($rawUnitKerja) : null;
        $rawGolongan = $request->input('golongan');
        $golongan = is_string($rawGolongan) && trim($rawGolongan) !== '' ? trim($rawGolongan) : null;
        $perPage = max(1, min(100, $request->integer('per_page', 15)));

        return Inertia::render('kenaikan-pangkat/eligible/index', [
            'pegawaiList' => $monitoringService->getUpcomingKenaikanPangkat($bulan, $perPage, $unitKerja, $golongan, $tahun),
            'stats' => $monitoringService->getKpStats($bulan, $tahun, $unitKerja, $golongan),
            'bulanOptions' => $this->bulanOptions(),
            'tahunOptions' => $this->tahunOptions(),
            'filters' => [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'unit_kerja' => $unitKerja,
                'golongan' => $golongan,
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', UsulanKenaikanPangkat::class);

        /** @var Pegawai $authPegawai */
        $authPegawai = $request->user();

        $rawPegawaiId = $request->input('pegawai_id');
        $pegawaiId = is_string($rawPegawaiId) && trim($rawPegawaiId) !== '' ? trim($rawPegawaiId) : null;

        $pegawai = $pegawaiId !== null
            ? Pegawai::query()
                ->with([
                    'pangkat:id,kode,nama,golongan,ruang',
                    'riwayatPangkat' => fn ($q) => $q->aktif()->with('pangkat:id,kode,nama,golongan,ruang')->orderByDesc('tmt'),
                ])
                ->findOrFail($pegawaiId)
            : $authPegawai->loadMissing([
                'pangkat:id,kode,nama,golongan,ruang',
                'riwayatPangkat' => fn ($q) => $q->aktif()->with('pangkat:id,kode,nama,golongan,ruang')->orderByDesc('tmt'),
            ]);

        $pangkatAsal = $pegawai->riwayatPangkat->first()?->pangkat ?? $pegawai->pangkat;

        return Inertia::render('kenaikan-pangkat/usulan/form', [
            'usulan' => null,
            'pegawai' => $pegawai->only(['id', 'nip', 'nama_lengkap']),
            'pangkatAsal' => $pangkatAsal?->only(['id', 'kode', 'nama', 'golongan', 'ruang']),
            'pangkatOptions' => RefPangkat::query()->orderBy('urutan')->get(['id', 'kode', 'nama', 'golongan', 'ruang']),
            'bulanOptions' => $this->bulanOptions(),
            'tahunOptions' => $this->tahunOptions(),
            'filters' => [
                'bulan' => $request->filled('bulan') ? $request->integer('bulan') : null,
                'tahun' => $request->filled('tahun') ? $request->integer('tahun') : null,
            ],
        ]);
    }

    public function store(StoreUsulanKenaikanPangkatRequest $request): RedirectResponse
    {
        $this->authorize('create', UsulanKenaikanPangkat::class);

        try {
            $usulan = $this->service->createDraft($request->validated(), $request->user());
        } catch (PegawaiTidakEligibleException|DuplicateUsulanException $e) {
            throw ValidationException::withMessages([
                'pegawai_id' => $e->getMessage(),
            ]);
        }

        return redirect()->route('kenaikan-pangkat.usulan.show', $usulan);
    }

    public function show(Request $request, UsulanKenaikanPangkat $usulan): Response
    {
        $this->authorize('view', $usulan);

        $usulan->load([
            'pegawai:id,nip,nama_lengkap',
            'pangkatAsal:id,kode,nama,golongan,ruang',
            'pangkatTujuan:id,kode,nama,golongan,ruang',
            'approvalSteps' => fn ($q) => $q->orderBy('urutan')->with('approver:id,nip,nama_lengkap'),
            'stateHistory' => fn ($q) => $q->latest('created_at')->with('transitionedBy:id,nip,nama_lengkap'),
            'approverHistory' => fn ($q) => $q->latest('created_at')->with('user:id,nip,nama_lengkap'),
            'lampiran' => fn ($q) => $q->latest('created_at'),
            'pdfs',
            'suratPengantarRequest',
            'checklistSubmission.template.items',
            'checklistSubmission.items.item',
        ]);

        /** @var Pegawai $user */
        $user = $request->user();

        $submission = $usulan->checklistSubmission;
        $checklistData = $submission ? [
            'id' => $submission->id,
            'persentase' => $submission->persentase,
            'status_kelengkapan' => $submission->status_kelengkapan,
            'items' => $submission->items->map(fn (BerkasChecklistSubmissionItem $subItem): array => [
                'id' => $subItem->id,
                'berkas_checklist_item_id' => $subItem->berkas_checklist_item_id,
                'nama' => $subItem->item?->nama,
                'nama_berkas' => $subItem->item?->nama,
                'wajib' => $subItem->item?->wajib,
                'status' => $subItem->status,
                'catatan' => $subItem->catatan,
                'file_original_name' => $subItem->file_original_name,
                'validated_at' => $subItem->validated_at?->toISOString(),
                'item' => $subItem->item ? [
                    'id' => $subItem->item->id,
                    'kode' => $subItem->item->kode,
                    'nama' => $subItem->item->nama,
                    'wajib' => $subItem->item->wajib,
                ] : null,
            ])->values()->all(),
        ] : null;

        $lampiranData = $usulan->lampiran->map(fn (UsulanKpLampiran $file): array => [
            'id' => $file->id,
            'jenis' => $file->jenis,
            'jenis_lampiran' => $file->jenis,
            'nama_file' => $file->nama_file,
            'nama_file_asli' => $file->file_original_name,
            'file_original_name' => $file->file_original_name,
            'mime_type' => $file->file_mime,
            'size_bytes' => $file->file_size,
            'catatan' => $file->catatan,
            'created_at' => $file->created_at?->toISOString(),
        ])->values()->all();

        $timelineData = $usulan->stateHistory->map(fn ($history): array => [
            'id' => $history->id,
            'from_state' => $history->from_state,
            'to_state' => $history->to_state,
            'catatan' => $history->catatan,
            'created_at' => $history->created_at?->toISOString(),
            'actor' => $history->transitionedBy ? [
                'id' => $history->transitionedBy->id,
                'nip' => $history->transitionedBy->nip,
                'nama_lengkap' => $history->transitionedBy->nama_lengkap,
            ] : null,
        ])->values()->all();

        return Inertia::render('kenaikan-pangkat/usulan/show', [
            'usulan' => $usulan,
            'timeline' => $timelineData,
            'lampiran' => $lampiranData,
            'checklist' => $checklistData,
            'policy' => [
                'update' => $user->can('update', $usulan),
                'batalkan' => $user->can('batalkan', $usulan),
                'submit' => $user->can('submit', $usulan),
                'uploadChecklist' => $user->can('uploadChecklist', $usulan),
                'validateChecklist' => $user->can('validateChecklist', $usulan),
                'uploadLampiran' => $user->can('uploadLampiran', $usulan),
            ],
            'stateLabels' => $this->stateLabels(),
            'stateBadgeVariants' => $this->stateBadgeVariants(),
        ]);
    }

    public function activity(UsulanKenaikanPangkat $usulan): JsonResponse
    {
        $this->authorize('view', $usulan);

        $activityLog = Activity::query()
            ->where(function ($query) use ($usulan): void {
                $query->where(function ($query) use ($usulan): void {
                    $query->where('subject_type', UsulanKenaikanPangkat::class)
                        ->where('subject_id', $usulan->id);
                })
                    ->orWhere(function ($query) use ($usulan): void {
                        $query->where('subject_type', $usulan->getMorphClass())
                            ->where('subject_id', $usulan->id);
                    });
            })
            ->latest()
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => (string) $activity->id,
                'source' => 'activity_log',
                'event' => $activity->event,
                'description' => $activity->description,
                'subject_type' => $activity->subject_type,
                'attribute_changes' => $activity->attribute_changes,
                'timestamp' => $activity->created_at?->toISOString(),
            ]);

        $stateHistory = $usulan->stateHistory()
            ->latest()
            ->get()
            ->map(fn ($history): array => [
                'id' => $history->id,
                'source' => 'state_history',
                'event' => 'state_transition',
                'from_state' => $history->from_state,
                'to_state' => $history->to_state,
                'catatan' => $history->catatan,
                'actor_id' => $history->transitioned_by,
                'timestamp' => $history->created_at?->toISOString(),
            ]);

        $approverHistory = $usulan->approverHistory()
            ->latest()
            ->get()
            ->map(fn ($history): array => [
                'id' => $history->id,
                'source' => 'approver_history',
                'event' => $history->action,
                'step_urutan' => $history->step_urutan,
                'catatan' => $history->catatan,
                'actor_id' => $history->user_id,
                'timestamp' => $history->created_at?->toISOString(),
            ]);

        $timeline = Collection::make()
            ->merge($activityLog)
            ->merge($stateHistory)
            ->merge($approverHistory)
            ->sortByDesc('timestamp')
            ->values();

        return response()->json([
            'data' => $timeline,
        ]);
    }

    public function edit(UsulanKenaikanPangkat $usulan): Response
    {
        $this->authorize('update', $usulan);

        $usulan->load([
            'pegawai:id,nip,nama_lengkap',
            'pangkatAsal:id,kode,nama,golongan,ruang',
            'pangkatTujuan:id,kode,nama,golongan,ruang',
        ]);

        return Inertia::render('kenaikan-pangkat/usulan/form', [
            'usulan' => [
                'id' => $usulan->id,
                'pegawai_id' => $usulan->pegawai_id,
                'ref_pangkat_asal_id' => $usulan->ref_pangkat_asal_id,
                'ref_pangkat_tujuan_id' => $usulan->ref_pangkat_tujuan_id,
                'periode_usul_bulan' => $usulan->periode_usul_bulan,
                'periode_usul_tahun' => $usulan->periode_usul_tahun,
                'catatan_pengusul' => $usulan->catatan_pengusul,
                'pangkat_asal' => $usulan->pangkatAsal?->only(['id', 'kode', 'nama', 'golongan', 'ruang']),
                'pangkat_tujuan' => $usulan->pangkatTujuan?->only(['id', 'kode', 'nama', 'golongan', 'ruang']),
            ],
            'pegawai' => $usulan->pegawai?->only(['id', 'nip', 'nama_lengkap']),
            'pangkatAsal' => $usulan->pangkatAsal?->only(['id', 'kode', 'nama', 'golongan', 'ruang']),
            'pangkatOptions' => RefPangkat::query()->orderBy('urutan')->get(['id', 'kode', 'nama', 'golongan', 'ruang']),
            'bulanOptions' => $this->bulanOptions(),
            'tahunOptions' => $this->tahunOptions(),
        ]);
    }

    public function update(UpdateUsulanKenaikanPangkatRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('update', $usulan);

        try {
            $this->service->update($usulan, $request->validated());
        } catch (PegawaiTidakEligibleException|DuplicateUsulanException $e) {
            throw ValidationException::withMessages([
                'pegawai_id' => $e->getMessage(),
            ]);
        } catch (TransitionNotFound) {
            throw ValidationException::withMessages([
                'usulan' => 'Status usulan sudah berubah dan tidak dapat diperbarui.',
            ]);
        }

        return redirect()->route('kenaikan-pangkat.usulan.show', $usulan);
    }

    public function destroy(UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('delete', $usulan);

        $usulan->delete();

        return redirect()->route('kenaikan-pangkat.usulan.index');
    }

    public function submit(SubmitUsulanKenaikanPangkatRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('submit', $usulan);

        $checklistId = $request->validated('checklist_submission_id');
        $checklist = is_string($checklistId) && $checklistId !== ''
            ? $usulan->checklistSubmission()->with(['template.items', 'items'])->find($checklistId)
            : null;

        try {
            $this->service->submit($usulan, $request->user(), $checklist);
        } catch (BerkasBelumLengkapException $e) {
            throw ValidationException::withMessages([
                'checklist' => $e->getMessage(),
            ]);
        } catch (TransitionNotFound) {
            throw ValidationException::withMessages([
                'usulan' => 'Status usulan sudah berubah dan tidak dapat diajukan.',
            ]);
        }

        return redirect()->route('kenaikan-pangkat.usulan.show', $usulan);
    }

    public function batalkan(BatalkanUsulanRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('batalkan', $usulan);

        try {
            $this->service->batalkan($usulan, $request->user(), $request->validated('alasan'));
        } catch (TransitionNotFound) {
            throw ValidationException::withMessages([
                'usulan' => 'Status usulan sudah berubah dan tidak dapat dibatalkan.',
            ]);
        }

        return redirect()->route('kenaikan-pangkat.usulan.show', $usulan);
    }

    public function uploadChecklist(
        UploadChecklistItemRequest $request,
        UsulanKenaikanPangkat $usulan,
        BerkasChecklistSubmissionItem $item,
    ): RedirectResponse {
        $this->authorize('uploadChecklist', $usulan);

        $submission = $usulan->checklistSubmission;

        abort_unless(
            $submission !== null && $item->berkas_checklist_submission_id === $submission->id,
            404,
        );

        $this->checklistBerkasService->uploadFile($item, $request->file('file'));
        $this->checklistBerkasService->recalculatePersentase($submission->fresh());

        return back()->with('success', 'Berkas checklist berhasil diunggah.');
    }

    public function validateChecklist(
        ValidateChecklistItemRequest $request,
        UsulanKenaikanPangkat $usulan,
        BerkasChecklistSubmissionItem $item,
    ): RedirectResponse {
        $this->authorize('validateChecklist', $usulan);

        $submission = $usulan->checklistSubmission;

        abort_unless(
            $submission !== null && $item->berkas_checklist_submission_id === $submission->id,
            404,
        );

        /** @var Pegawai $validator */
        $validator = $request->user();

        $this->checklistBerkasService->validateItem(
            $item,
            $validator,
            $request->string('status')->toString(),
            $request->validated('catatan'),
        );
        $this->checklistBerkasService->recalculatePersentase($submission->fresh());

        return back()->with('success', 'Status berkas checklist berhasil diperbarui.');
    }

    public function storeLampiran(StoreLampiranUsulanRequest $request, UsulanKenaikanPangkat $usulan): RedirectResponse
    {
        $this->authorize('uploadLampiran', $usulan);

        $file = $request->file('file');
        $path = Storage::disk('local')->putFile("usulan-kp/lampiran/{$usulan->id}", $file);

        /** @var Pegawai $uploader */
        $uploader = $request->user();

        UsulanKpLampiran::query()->create([
            'usulan_kenaikan_pangkat_id' => $usulan->id,
            'jenis' => $request->string('jenis')->value() ?: 'pendukung',
            'nama_file' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_original_name' => $file->getClientOriginalName(),
            'file_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'file_size' => (int) $file->getSize(),
            'uploaded_by' => $uploader->id,
            'catatan' => $request->validated('catatan'),
        ]);

        return back()->with('success', 'Lampiran tambahan berhasil diunggah.');
    }

    public function destroyLampiran(UsulanKenaikanPangkat $usulan, UsulanKpLampiran $lampiran): RedirectResponse
    {
        $this->authorize('uploadLampiran', $usulan);

        abort_unless($lampiran->usulan_kenaikan_pangkat_id === $usulan->id, 404);

        if (filled($lampiran->file_path) && Storage::disk('local')->exists($lampiran->file_path)) {
            Storage::disk('local')->delete($lampiran->file_path);
        }

        $lampiran->delete();

        return back()->with('success', 'Lampiran berhasil dihapus.');
    }

    /**
     * Daftar label state usulan kenaikan pangkat.
     *
     * @return array<string, string>
     */
    private function stateLabels(): array
    {
        return [
            'DRAFT' => 'Draft',
            'DIAJUKAN' => 'Diajukan',
            'DIVERIFIKASI_KASUBBAG' => 'Diverifikasi Kasubbag',
            'DIVERIFIKASI_SEKRETARIS' => 'Diverifikasi Sekretaris',
            'MENUNGGU_NOMOR_SURAT' => 'Menunggu Nomor Surat (SiSukma)',
            'DITANDATANGANI_KETUA' => 'Ditandatangani Ketua',
            'DIKIRIM_BIRO' => 'Dikirim Biro',
            'MENUNGGU_SK' => 'Menunggu SK',
            'SELESAI_SK_TERBIT' => 'Selesai SK Terbit',
            'PERLU_PERBAIKAN' => 'Perlu Perbaikan',
            'DITOLAK' => 'Ditolak',
            'DIBATALKAN' => 'Dibatalkan',
        ];
    }

    /**
     * Daftar varian badge UI per state usulan KP.
     *
     * @return array<string, string>
     */
    private function stateBadgeVariants(): array
    {
        return [
            'DRAFT' => 'secondary',
            'DIAJUKAN' => 'outline',
            'DIVERIFIKASI_KASUBBAG' => 'outline',
            'DIVERIFIKASI_SEKRETARIS' => 'outline',
            'MENUNGGU_NOMOR_SURAT' => 'secondary',
            'DITANDATANGANI_KETUA' => 'default',
            'DIKIRIM_BIRO' => 'outline',
            'MENUNGGU_SK' => 'secondary',
            'SELESAI_SK_TERBIT' => 'default',
            'PERLU_PERBAIKAN' => 'destructive',
            'DITOLAK' => 'destructive',
            'DIBATALKAN' => 'secondary',
        ];
    }

    /**
     * Pilihan periode bulan (1..12) untuk filter dan form.
     *
     * @return array<int, array{value: int, label: string}>
     */
    private function bulanOptions(): array
    {
        return [
            ['value' => 1, 'label' => 'Januari'],
            ['value' => 2, 'label' => 'Februari'],
            ['value' => 3, 'label' => 'Maret'],
            ['value' => 4, 'label' => 'April'],
            ['value' => 5, 'label' => 'Mei'],
            ['value' => 6, 'label' => 'Juni'],
            ['value' => 7, 'label' => 'Juli'],
            ['value' => 8, 'label' => 'Agustus'],
            ['value' => 9, 'label' => 'September'],
            ['value' => 10, 'label' => 'Oktober'],
            ['value' => 11, 'label' => 'November'],
            ['value' => 12, 'label' => 'Desember'],
        ];
    }

    /**
     * Pilihan periode tahun untuk filter dan form.
     *
     * @return array<int, int>
     */
    private function tahunOptions(): array
    {
        $currentYear = (int) now()->format('Y');

        return range($currentYear, $currentYear + 3);
    }
}
