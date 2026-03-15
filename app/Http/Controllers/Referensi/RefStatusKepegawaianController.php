<?php

namespace App\Http\Controllers\Referensi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referensi\StoreRefStatusKepegawaianRequest;
use App\Http\Requests\Referensi\UpdateRefStatusKepegawaianRequest;
use App\Models\RefStatusKepegawaian;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RefStatusKepegawaianController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', RefStatusKepegawaian::class);

        $statusKepegawaian = RefStatusKepegawaian::query()
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('kode', 'like', "%{$search}%")
                        ->orWhere('nama', 'like', "%{$search}%");
                });
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('referensi/status-kepegawaian/index', [
            'statusKepegawaian' => $statusKepegawaian,
            'filters' => request()->only(['search']),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RefStatusKepegawaian::class);

        return Inertia::render('referensi/status-kepegawaian/create');
    }

    public function store(StoreRefStatusKepegawaianRequest $request): RedirectResponse
    {
        RefStatusKepegawaian::query()->create($request->validated());

        return redirect()
            ->route('referensi.status-kepegawaian.index')
            ->with('success', 'Status kepegawaian berhasil ditambahkan.');
    }

    public function edit(RefStatusKepegawaian $statusKepegawaian): Response
    {
        $this->authorize('update', $statusKepegawaian);

        return Inertia::render('referensi/status-kepegawaian/edit', [
            'statusKepegawaian' => $statusKepegawaian,
        ]);
    }

    public function update(UpdateRefStatusKepegawaianRequest $request, RefStatusKepegawaian $statusKepegawaian): RedirectResponse
    {
        $statusKepegawaian->update($request->validated());

        return redirect()
            ->route('referensi.status-kepegawaian.index')
            ->with('success', 'Status kepegawaian berhasil diperbarui.');
    }

    public function destroy(RefStatusKepegawaian $statusKepegawaian): RedirectResponse
    {
        $this->authorize('delete', $statusKepegawaian);

        $statusKepegawaian->delete();

        return redirect()
            ->route('referensi.status-kepegawaian.index')
            ->with('success', 'Status kepegawaian berhasil dihapus.');
    }
}
