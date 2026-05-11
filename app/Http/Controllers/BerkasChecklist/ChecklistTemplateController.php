<?php

namespace App\Http\Controllers\BerkasChecklist;

use App\Http\Controllers\Controller;
use App\Http\Requests\BerkasChecklist\StoreChecklistTemplateRequest;
use App\Http\Requests\BerkasChecklist\UpdateChecklistTemplateRequest;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChecklistTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BerkasChecklistTemplate::class);

        $templates = BerkasChecklistTemplate::query()
            ->withCount('items')
            ->when($request->filled('jenis'), fn ($query): mixed => $query->where('jenis', $request->string('jenis')))
            ->orderBy('jenis')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        $domains = BerkasChecklistTemplate::query()
            ->select('jenis')
            ->distinct()
            ->orderBy('jenis')
            ->pluck('jenis');

        return Inertia::render('admin/checklist-template/index', [
            'templates' => $templates,
            'filters' => $request->only(['jenis']),
            'domains' => $domains,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', BerkasChecklistTemplate::class);

        return Inertia::render('admin/checklist-template/form', [
            'template' => null,
        ]);
    }

    public function store(StoreChecklistTemplateRequest $request): RedirectResponse
    {
        $this->authorize('create', BerkasChecklistTemplate::class);

        DB::transaction(function () use ($request): void {
            $validated = $request->validated();
            $template = BerkasChecklistTemplate::query()->create(Arr::except($validated, ['items']));

            $this->syncItems($template, $validated['items'] ?? []);
        });

        return redirect()
            ->route('admin.checklist-template.index')
            ->with('success', 'Template checklist berhasil ditambahkan.');
    }

    public function edit(BerkasChecklistTemplate $template): Response
    {
        $this->authorize('update', $template);

        return Inertia::render('admin/checklist-template/form', [
            'template' => $template->load(['items' => fn ($query) => $query->ordered()]),
        ]);
    }

    public function update(UpdateChecklistTemplateRequest $request, BerkasChecklistTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);

        DB::transaction(function () use ($request, $template): void {
            $validated = $request->validated();
            $template->update(Arr::except($validated, ['kode', 'items']));

            $this->syncItems($template, $validated['items'] ?? []);
        });

        return redirect()
            ->route('admin.checklist-template.index')
            ->with('success', 'Template checklist berhasil diperbarui.');
    }

    public function destroy(BerkasChecklistTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        if ($template->submissions()->exists()) {
            return $this->cannotDeleteResponse();
        }

        try {
            $template->delete();
        } catch (QueryException) {
            return $this->cannotDeleteResponse();
        }

        return redirect()
            ->route('admin.checklist-template.index')
            ->with('success', 'Template checklist berhasil dihapus.');
    }

    /**
     * @param  array<int, array{id?: string|null, kode: string, nama: string, wajib?: bool, urutan?: int|null}>  $items
     */
    private function syncItems(BerkasChecklistTemplate $template, array $items): void
    {
        $keptIds = [];

        foreach ($items as $index => $item) {
            $payload = [
                'kode' => $item['kode'],
                'nama' => $item['nama'],
                'wajib' => (bool) ($item['wajib'] ?? false),
                'urutan' => $item['urutan'] ?? $index + 1,
            ];

            if (! empty($item['id'])) {
                $templateItem = $template->items()->whereKey($item['id'])->firstOrFail();
                $templateItem->update($payload);
                $keptIds[] = $templateItem->id;

                continue;
            }

            $keptIds[] = $template->items()->create($payload)->id;
        }

        $template->items()
            ->when($keptIds !== [], fn ($query): mixed => $query->whereNotIn('id', $keptIds))
            ->delete();
    }

    private function cannotDeleteResponse(): RedirectResponse
    {
        return redirect()
            ->route('admin.checklist-template.index')
            ->with('error', 'Template checklist tidak dapat dihapus karena sudah digunakan.');
    }
}
