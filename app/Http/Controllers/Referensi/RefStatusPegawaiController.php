<?php

namespace App\Http\Controllers\Referensi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referensi\StoreRefStatusPegawaiRequest;
use App\Http\Requests\Referensi\UpdateRefStatusPegawaiRequest;
use App\Models\RefStatusPegawai;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RefStatusPegawaiController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', RefStatusPegawai::class);
        $statusPegawai = RefStatusPegawai::query()
            ->when(request('search'), function ($query, $search) {
                $query->where('kode', 'like', "%{$search}%")->orWhere('nama', 'like', "%{$search}%");
            })
            ->orderBy('nama')->paginate(10)->withQueryString();

        return Inertia::render('referensi/status-pegawai/index', [
            'statusPegawai' => $statusPegawai,
            'filters' => request()->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RefStatusPegawai::class);

        return Inertia::render('referensi/status-pegawai/create');
    }

    public function store(StoreRefStatusPegawaiRequest $request): RedirectResponse
    {
        RefStatusPegawai::query()->create($request->validated());

        return redirect()->route('referensi.status-pegawai.index')->with('success', 'Status pegawai berhasil ditambahkan.');
    }

    public function edit(RefStatusPegawai $statusPegawai): Response
    {
        $this->authorize('update', $statusPegawai);

        return Inertia::render('referensi/status-pegawai/edit', ['statusPegawai' => $statusPegawai]);
    }

    public function update(UpdateRefStatusPegawaiRequest $request, RefStatusPegawai $statusPegawai): RedirectResponse
    {
        $statusPegawai->update($request->validated());

        return redirect()->route('referensi.status-pegawai.index')->with('success', 'Status pegawai berhasil diperbarui.');
    }

    public function destroy(RefStatusPegawai $statusPegawai): RedirectResponse
    {
        $this->authorize('delete', $statusPegawai);
        $statusPegawai->delete();

        return redirect()->route('referensi.status-pegawai.index')->with('success', 'Status pegawai berhasil dihapus.');
    }
}
