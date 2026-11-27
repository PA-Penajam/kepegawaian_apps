<?php

namespace App\Http\Controllers\Cuti;

use App\Enums\JenisJabatan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cuti\StoreCutiKewenanganRequest;
use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiKewenangan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class KewenanganAdminController extends Controller
{
    /**
     * Menampilkan matriks kewenangan persetujuan cuti ke PTA/MA beserta daftar opsi referensi.
     */
    public function index(): Response
    {
        $rules = CutiKewenangan::query()
            ->with('jenisCuti:kode,nama')
            ->orderBy('jenis_cuti_kode')
            ->orderBy('jenis_jabatan')
            ->get();

        $jenisOptions = CutiJenisMaster::query()
            ->where('aktif', true)
            ->orderBy('kode')
            ->get(['kode', 'nama']);

        $jabatanOptions = array_map(
            static fn (JenisJabatan $item): array => [
                'value' => $item->value,
                'label' => $item->label(),
            ],
            JenisJabatan::cases(),
        );

        return Inertia::render('cuti/admin/kewenangan', [
            'rules' => $rules,
            'jenisOptions' => $jenisOptions,
            'jabatanOptions' => $jabatanOptions,
        ]);
    }

    /**
     * Menyimpan aturan baru matriks kewenangan eksternal.
     */
    public function store(StoreCutiKewenanganRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        CutiKewenangan::query()->create([
            ...$validated,
            'aktif' => (bool) ($validated['aktif'] ?? true),
        ]);

        return back()->with('success', 'Aturan matriks kewenangan eksternal berhasil ditambahkan.');
    }

    /**
     * Memperbarui aturan matriks kewenangan eksternal yang sudah ada.
     */
    public function update(StoreCutiKewenanganRequest $request, string $id): RedirectResponse
    {
        $rule = CutiKewenangan::query()->findOrFail($id);
        $validated = $request->validated();

        $rule->update([
            ...$validated,
            'aktif' => (bool) ($validated['aktif'] ?? true),
        ]);

        return back()->with('success', 'Aturan matriks kewenangan eksternal berhasil diperbarui.');
    }

    /**
     * Menghapus aturan matriks kewenangan eksternal agar kembali ke kewenangan internal.
     */
    public function destroy(string $id): RedirectResponse
    {
        $rule = CutiKewenangan::query()->findOrFail($id);
        $rule->delete();

        return back()->with('success', 'Aturan matriks kewenangan eksternal berhasil dihapus.');
    }
}
