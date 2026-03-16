<?php

namespace App\Http\Controllers\Referensi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referensi\StoreRefJenisDokumenRequest;
use App\Http\Requests\Referensi\UpdateRefJenisDokumenRequest;
use App\Models\RefJenisDokumen;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RefJenisDokumenController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', RefJenisDokumen::class);

        $jenisDokumen = RefJenisDokumen::query()
            ->when(request('search'), function ($query, $search) {
                $query->where('nama', 'like', "%{$search}%");
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('referensi/jenis-dokumen/index', [
            'jenisDokumen' => $jenisDokumen,
            'filters' => request()->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RefJenisDokumen::class);

        return Inertia::render('referensi/jenis-dokumen/create');
    }

    public function store(StoreRefJenisDokumenRequest $request): RedirectResponse
    {
        RefJenisDokumen::query()->create($request->validated());

        return redirect()
            ->route('referensi.jenis-dokumen.index')
            ->with('success', 'Jenis dokumen berhasil ditambahkan.');
    }

    public function edit(RefJenisDokumen $jenisDokumen): Response
    {
        $this->authorize('update', $jenisDokumen);

        return Inertia::render('referensi/jenis-dokumen/edit', [
            'jenisDokumen' => $jenisDokumen,
        ]);
    }

    public function update(UpdateRefJenisDokumenRequest $request, RefJenisDokumen $jenisDokumen): RedirectResponse
    {
        $jenisDokumen->update($request->validated());

        return redirect()
            ->route('referensi.jenis-dokumen.index')
            ->with('success', 'Jenis dokumen berhasil diperbarui.');
    }

    public function destroy(RefJenisDokumen $jenisDokumen): RedirectResponse
    {
        $this->authorize('delete', $jenisDokumen);

        $jenisDokumen->delete();

        return redirect()
            ->route('referensi.jenis-dokumen.index')
            ->with('success', 'Jenis dokumen berhasil dihapus.');
    }
}
