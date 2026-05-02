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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PegawaiController extends Controller
{
    public function __construct(
        private SubmitPengajuanPerubahanDataService $submitPengajuanPerubahanData
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Pegawai::class);

        $sortBy = match ($request->input('sort_by')) {
            'nama' => 'nama_lengkap',
            'nip' => 'nip',
            default => null,
        };

        $sortDirection = strtolower((string) $request->input('sort_dir', 'asc')) === 'desc'
            ? 'desc'
            : 'asc';

        $pegawai = Pegawai::query()
            ->with(['pangkat', 'jabatan', 'unitKerja'])
            ->search($request->input('search'), ['nip', 'nama_lengkap'])
            ->filter([
                'ref_unit_kerja_id' => $request->input('unit_kerja'),
                'status_pegawai' => $request->input('status_pegawai'),
            ])
            ->when($request->filled('jabatan'), function ($query) use ($request): void {
                $query->where('ref_jabatan_id', $request->input('jabatan'));
            })
            ->when($request->filled('golongan'), function ($query) use ($request): void {
                $query->whereHas('pangkat', function ($pangkatQuery) use ($request): void {
                    $pangkatQuery->where('kode', $request->input('golongan'));
                });
            })
            ->when($request->input('sort_by') === 'pangkat', function ($query) use ($sortDirection): void {
                $query->orderBy(
                    RefPangkat::query()
                        ->select('kode')
                        ->whereColumn('ref_pangkat.id', 'pegawai.ref_pangkat_id')
                        ->limit(1),
                    $sortDirection,
                );
            })
            ->when($request->input('sort_by') === 'jabatan', function ($query) use ($sortDirection): void {
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
                'search' => $request->input('search'),
                'golongan' => $request->input('golongan'),
                'jabatan' => $request->input('jabatan'),
                'unit_kerja' => $request->input('unit_kerja'),
                'status_pegawai' => $request->input('status_pegawai'),
                'sort_by' => $request->input('sort_by'),
                'sort_dir' => $request->input('sort_dir'),
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
            'enums' => [
                'jenisKelamin' => JenisKelamin::cases(),
                'agama' => Agama::cases(),
                'golonganDarah' => GolonganDarah::cases(),
                'statusPerkawinan' => StatusPerkawinan::cases(),
                'statusPegawai' => StatusPegawai::cases(),
                'statusKepegawaian' => StatusKepegawaian::cases(),
                'pendidikanTerakhir' => JenjangPendidikan::cases(),
            ],
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
            'enums' => [
                'jenisKelamin' => JenisKelamin::cases(),
                'agama' => Agama::cases(),
                'golonganDarah' => GolonganDarah::cases(),
                'statusPerkawinan' => StatusPerkawinan::cases(),
                'statusPegawai' => StatusPegawai::cases(),
                'statusKepegawaian' => StatusKepegawaian::cases(),
                'pendidikanTerakhir' => JenjangPendidikan::cases(),
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePegawaiRequest $request, Pegawai $pegawai): RedirectResponse
    {
        Gate::authorize('update', $pegawai);

        try {
            // Intersepsi operator: jadikan pending request alih-alih update langsung
            if ($request->user()?->isOperator()) {
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
                            ['nama_lengkap', 'tempat_lahir', 'tanggal_lahir', 'status_perkawinan', 'alamat', 'no_telepon', 'email']
                        ),
                        'lampiran' => [],
                    ],
                    'operator',
                );

                return to_route('kepegawaian.pegawai.show', $pegawai)
                    ->with('success', 'Perubahan disimpan sebagai pengajuan (Pending) untuk divalidasi.');
            }

            $pegawai->update($request->safe()->except(['password', 'password_confirmation']));

            if ($request->filled('password')) {
                $pegawai->update(['password' => bcrypt($request->validated('password'))]);
            }

            return to_route('kepegawaian.pegawai.show', $pegawai)
                ->with('success', 'Data pegawai berhasil diperbarui.');
        } catch (\Exception $e) {
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
            $pegawai->delete();

            return to_route('kepegawaian.pegawai.index')
                ->with('success', 'Data pegawai berhasil dihapus.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat menghapus data pegawai. Silakan coba lagi.');
        }
    }

    /**
     * Update foto pegawai.
     */
    public function updateFoto(UpdateFotoPegawaiRequest $request, Pegawai $pegawai): RedirectResponse
    {
        try {
            $path = "fotos/{$pegawai->id}.webp";

            $manager = new ImageManager(new Driver);
            $image = $manager->read($request->file('foto')->getRealPath());
            $image->coverDown(400, 400, 'center');
            $encoded = $image->toWebp(quality: 80);

            Storage::disk('public')->put($path, $encoded->toString());

            $pegawai->update(['foto' => $path]);

            return back()->with('success', 'Foto pegawai berhasil diperbarui.');
        } catch (\Exception $e) {
            return back()->with('error', 'Terjadi kesalahan saat mengunggah foto. Pastikan file gambar valid dan silakan coba lagi.');
        }
    }
}
