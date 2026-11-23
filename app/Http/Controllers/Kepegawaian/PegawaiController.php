<?php

namespace App\Http\Controllers\Kepegawaian;

use App\Enums\Agama;
use App\Enums\GolonganDarah;
use App\Enums\JenisKelamin;
use App\Enums\JenjangPendidikan;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusPerkawinan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kepegawaian\StorePegawaiRequest;
use App\Http\Requests\Kepegawaian\UpdateFotoPegawaiRequest;
use App\Http\Requests\Kepegawaian\UpdatePegawaiRequest;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\RefPangkat;
use App\Models\RefUnitKerja;
use App\Services\PengajuanPerubahanData\SubmitPengajuanPerubahanDataService;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PegawaiController extends Controller
{
    /** @var array<int, string> */
    private const OPERATOR_PROFILE_FIELDS = [
        'nama_lengkap',
        'tempat_lahir',
        'tanggal_lahir',
        'status_perkawinan',
        'alamat',
        'no_telepon',
        'email',
    ];

    /** @var array<int, string> */
    private const DATE_FIELDS = [
        'tanggal_lahir',
        'tmt_cpns',
        'tmt_pns',
        'tanggal_masuk',
        'tanggal_pensiun_bup',
    ];

    public function __construct(
        private SubmitPengajuanPerubahanDataService $submitPengajuanPerubahanData
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Pegawai::class);

        $rawSortBy = $request->input('sort_by');
        $sortByInput = is_string($rawSortBy) ? $rawSortBy : null;
        $sortBy = match ($sortByInput) {
            'nama' => 'nama_lengkap',
            'nip' => 'nip',
            default => null,
        };

        $rawSortDir = $request->input('sort_dir');
        $sortDirection = is_string($rawSortDir) && strtolower($rawSortDir) === 'desc'
            ? 'desc'
            : 'asc';

        $rawSearch = $request->input('search');
        $search = is_string($rawSearch) ? $rawSearch : null;

        $rawUnitKerja = $request->input('unit_kerja');
        $unitKerja = is_string($rawUnitKerja) && $rawUnitKerja !== '' ? $rawUnitKerja : null;

        $rawStatusPegawai = $request->input('status_pegawai');
        $statusPegawai = is_string($rawStatusPegawai) && $rawStatusPegawai !== '' ? $rawStatusPegawai : null;

        $rawJabatan = $request->input('jabatan');
        $jabatan = is_string($rawJabatan) && $rawJabatan !== '' ? $rawJabatan : null;

        $rawGolongan = $request->input('golongan');
        $golongan = is_string($rawGolongan) && $rawGolongan !== '' ? $rawGolongan : null;

        $pegawai = Pegawai::query()
            ->with(['pangkat', 'jabatan', 'unitKerja'])
            ->search($search, ['nip', 'nama_lengkap'])
            ->filter([
                'ref_unit_kerja_id' => $unitKerja,
                'status_pegawai' => $statusPegawai,
            ])
            ->when($jabatan !== null, function ($query) use ($jabatan): void {
                $query->where('ref_jabatan_id', $jabatan);
            })
            ->when($golongan !== null, function ($query) use ($golongan): void {
                $query->whereHas('pangkat', function ($pangkatQuery) use ($golongan): void {
                    $pangkatQuery->where('kode', $golongan);
                });
            })
            ->when($sortByInput === 'pangkat', function ($query) use ($sortDirection): void {
                $query->orderBy(
                    RefPangkat::query()
                        ->select('kode')
                        ->whereColumn('ref_pangkat.id', 'pegawai.ref_pangkat_id')
                        ->limit(1),
                    $sortDirection,
                );
            })
            ->when($sortByInput === 'jabatan', function ($query) use ($sortDirection): void {
                $query->orderBy(
                    RefJabatan::query()
                        ->select('nama')
                        ->whereColumn('ref_jabatan.id', 'pegawai.ref_jabatan_id')
                        ->limit(1),
                    $sortDirection,
                );
            })
            ->sorted($sortBy, $sortDirection)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('kepegawaian/pegawai/index', [
            'pegawai' => $pegawai,
            'filters' => [
                'search' => $search,
                'golongan' => $golongan,
                'jabatan' => $jabatan,
                'unit_kerja' => $unitKerja,
                'status_pegawai' => $statusPegawai,
                'sort_by' => $sortByInput,
                'sort_dir' => is_string($rawSortDir) ? $rawSortDir : null,
            ],
            'refJabatan' => RefJabatan::query()
                ->select(['id', 'nama'])
                ->orderBy('nama')
                ->get(),
            'filterOptions' => [
                'golongan' => RefPangkat::query()
                    ->select(['id', 'kode', 'nama'])
                    ->orderBy('urutan')
                    ->orderBy('nama')
                    ->get(),
                'unitKerja' => RefUnitKerja::query()
                    ->select(['id', 'nama'])
                    ->orderBy('urutan')
                    ->orderBy('nama')
                    ->get(),
                'statusPegawai' => array_map(
                    static fn (StatusPegawai $status): string => $status->value,
                    StatusPegawai::cases(),
                ),
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        Gate::authorize('create', Pegawai::class);

        return Inertia::render('kepegawaian/pegawai/create', [
            'refPangkat' => RefPangkat::orderBy('urutan')->get(['id', 'kode', 'nama']),
            'refJabatan' => RefJabatan::orderBy('nama')->get(['id', 'nama', 'jenis_jabatan']),
            'refUnitKerja' => RefUnitKerja::orderBy('urutan')->get(['id', 'nama']),
            'enums' => $this->enumOptions(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePegawaiRequest $request): RedirectResponse
    {
        Gate::authorize('create', Pegawai::class);

        try {
            $pegawai = Pegawai::query()->create($request->validated());

            return to_route('kepegawaian.pegawai.show', $pegawai)
                ->with('success', 'Data pegawai berhasil ditambahkan.');
        } catch (\Exception $e) {
            report($e);

            return back()->with('error', 'Terjadi kesalahan saat menambahkan data pegawai. Silakan coba lagi.');
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Pegawai $pegawai): Response
    {
        Gate::authorize('view', $pegawai);

        return Inertia::render('kepegawaian/pegawai/show', [
            'pegawai' => $pegawai->load([
                'pangkat', 'jabatan', 'unitKerja',
                'riwayatPangkat.pangkat',
                'riwayatJabatan.jabatan', 'riwayatJabatan.unitKerja',
                'riwayatPendidikan',
                'riwayatDiklat.jenisDiklat',
                'keluarga',
                'penghargaan.jenisPenghargaan',
                'hukumanDisiplin.jenisHukumanDisiplin',
                'dokumenPegawai',
            ]),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Pegawai $pegawai): Response
    {
        Gate::authorize('update', $pegawai);

        return Inertia::render('kepegawaian/pegawai/edit', [
            'pegawai' => $pegawai->load(['pangkat', 'jabatan', 'unitKerja']),
            'refPangkat' => RefPangkat::orderBy('urutan')->get(['id', 'kode', 'nama']),
            'refJabatan' => RefJabatan::orderBy('nama')->get(['id', 'nama', 'jenis_jabatan']),
            'refUnitKerja' => RefUnitKerja::orderBy('urutan')->get(['id', 'nama']),
            'enums' => $this->enumOptions(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePegawaiRequest $request, Pegawai $pegawai): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        try {
            // Intersepsi operator: tolak perubahan field non-profil secara eksplisit,
            // lalu jadikan perubahan profil sebagai pengajuan pending.
            if ($request->user()?->isOperator()) {
                $this->ensureOperatorOnlyModifiesProfileFields($request, $pegawai);

                $this->submitPengajuanPerubahanData->handle(
                    $request->user(),
                    [
                        'domain' => 'profil_pribadi',
                        'aksi' => 'update',
                        'target_type' => 'pegawai',
                        'target_id' => $pegawai->id,
                        'subject_pegawai_id' => $pegawai->id,
                        'after_payload' => Arr::only(
                            $request->safe()->except(['password', 'password_confirmation']),
                            self::OPERATOR_PROFILE_FIELDS
                        ),
                        'lampiran' => [],
                    ],
                    'operator',
                );

                return to_route('kepegawaian.pegawai.show', $pegawai)
                    ->with('success', 'Perubahan disimpan sebagai pengajuan (Pending) untuk divalidasi.');
            }

            DB::transaction(function () use ($request, $pegawai): void {
                // Mode SSO-only: abaikan field password agar kredensial lokal tidak berubah
                $pegawai->fill($request->safe()->except(['password', 'password_confirmation']));
                $pegawai->save();

                if ($pegawai->status_pegawai !== StatusPegawai::Aktif) {
                    $this->invalidatePegawaiSessions($pegawai);
                }
            });

            return to_route('kepegawaian.pegawai.show', $pegawai)
                ->with('success', 'Data pegawai berhasil diperbarui.');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            report($e);

            return back()->with('error', 'Terjadi kesalahan saat memperbarui data pegawai. Silakan coba lagi.');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Pegawai $pegawai): RedirectResponse
    {
        Gate::authorize('delete', $pegawai);

        try {
            DB::transaction(function () use ($pegawai): void {
                $this->invalidatePegawaiSessions($pegawai);
                $pegawai->delete();
            });

            return to_route('kepegawaian.pegawai.index')
                ->with('success', 'Data pegawai berhasil dihapus.');
        } catch (\Exception $e) {
            report($e);

            return back()->with('error', 'Terjadi kesalahan saat menghapus data pegawai. Silakan coba lagi.');
        }
    }

    /**
     * Tampilkan foto pegawai dari disk lokal secara terotorisasi.
     * Pegawai yang bersangkutan diizinkan memuat fotonya sendiri pada self-service.
     */
    public function showFoto(Request $request, Pegawai $pegawai): StreamedResponse
    {
        if (! $request->user()?->is($pegawai)) {
            Gate::authorize('view', $pegawai);
        }

        if (blank($pegawai->foto) || ! Storage::disk('local')->exists($pegawai->foto)) {
            abort(404, 'Foto pegawai tidak ditemukan.');
        }

        return Storage::disk('local')->response($pegawai->foto);
    }

    /**
     * Update foto pegawai.
     */
    public function updateFoto(UpdateFotoPegawaiRequest $request, Pegawai $pegawai): RedirectResponse
    {
        try {
            $path = "fotos/{$pegawai->id}.webp";
            $oldPath = $pegawai->foto;

            $manager = new ImageManager(new Driver);
            $image = $manager->read($request->file('foto')->getRealPath());
            $image->coverDown(400, 400, 'center');
            $encoded = $image->toWebp(quality: 80);

            Storage::disk('local')->put($path, $encoded->toString());

            if (filled($oldPath) && $oldPath !== $path && Storage::disk('local')->exists($oldPath)) {
                Storage::disk('local')->delete($oldPath);
            }

            $pegawai->forceFill(['foto' => $path])->save();

            return back()->with('success', 'Foto pegawai berhasil diperbarui.');
        } catch (\Exception $e) {
            report($e);

            return back()->with('error', 'Terjadi kesalahan saat mengunggah foto. Pastikan file gambar valid dan silakan coba lagi.');
        }
    }

    /**
     * Memastikan operator hanya mengubah kolom profil pribadi yang diizinkan.
     */
    private function ensureOperatorOnlyModifiesProfileFields(UpdatePegawaiRequest $request, Pegawai $pegawai): void
    {
        $errors = [];

        if ($request->filled('password')) {
            $errors['password'] = ['Operator tidak memiliki izin untuk mengubah kata sandi pegawai.'];
        }

        $validated = $request->safe()->except(['password', 'password_confirmation']);

        foreach ($validated as $field => $incomingValue) {
            if (in_array($field, self::OPERATOR_PROFILE_FIELDS, true)) {
                continue;
            }

            $existingRaw = $pegawai->getAttribute($field);
            $existingNorm = $this->normalizeFieldForComparison($field, $existingRaw);
            $incomingNorm = $this->normalizeFieldForComparison($field, $incomingValue);

            if ($existingNorm !== $incomingNorm) {
                $errors[$field] = ["Operator tidak memiliki izin untuk mengubah kolom {$field} secara langsung."];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function normalizeFieldForComparison(string $field, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            return Carbon::parse((string) $value)->toDateString();
        }

        return (string) $value;
    }

    /**
     * Mengosongkan remember token, mencabut token Sanctum, dan menghapus sesi database aktif milik pegawai.
     */
    private function invalidatePegawaiSessions(Pegawai $pegawai): void
    {
        $pegawai->forceFill(['remember_token' => null])->saveQuietly();
        $pegawai->tokens()->delete();

        $sessionTable = (string) config('session.table', 'sessions');
        if (Schema::hasTable($sessionTable)) {
            DB::table($sessionTable)->where('user_id', $pegawai->id)->delete();
        }
    }

    private function enumOptions(): array
    {
        return [
            'jenisKelamin' => JenisKelamin::cases(),
            'agama' => Agama::cases(),
            'golonganDarah' => GolonganDarah::cases(),
            'statusPerkawinan' => StatusPerkawinan::cases(),
            'statusPegawai' => StatusPegawai::cases(),
            'statusKepegawaian' => StatusKepegawaian::cases(),
            'pendidikanTerakhir' => JenjangPendidikan::cases(),
        ];
    }
}
