<?php

namespace App\Http\Controllers\Referensi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Referensi\StoreRefStatusKepegawaianRequest;
use App\Http\Requests\Referensi\UpdateRefStatusKepegawaianRequest;
use App\Models\RefStatusKepegawaian;
use App\Support\SqlLike;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RefStatusKepegawaianController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', RefStatusKepegawaian::class);

        $rawSearch = request('search');
        $search = is_string($rawSearch) && trim($rawSearch) !== '' ? $rawSearch : null;

        $statusKepegawaian = RefStatusKepegawaian::query()
            ->when($search !== null, function ($query) use ($search): void {
                SqlLike::whereContains($query, ['kode', 'nama'], $search);
            })
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('referensi/status-kepegawaian/index', [
            'statusKepegawaian' => $statusKepegawaian,
            'filters' => ['search' => $search],
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
