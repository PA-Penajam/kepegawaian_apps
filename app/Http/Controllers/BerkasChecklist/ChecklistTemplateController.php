<?php

namespace App\Http\Controllers\BerkasChecklist;

use App\Http\Controllers\Controller;
use App\Http\Requests\BerkasChecklist\StoreChecklistTemplateRequest;
use App\Http\Requests\BerkasChecklist\UpdateChecklistTemplateRequest;
use App\Models\BerkasChecklist\BerkasChecklistItem;
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

        $rawJenis = $request->input('jenis');
        $jenis = is_string($rawJenis) && trim($rawJenis) !== '' ? trim($rawJenis) : null;

        $templates = BerkasChecklistTemplate::query()
            ->withCount('items')
            ->when($jenis !== null, fn ($query): mixed => $query->where('jenis', $jenis))
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
            'filters' => ['jenis' => $jenis],
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
        $keptIds = array_values(array_filter(
            array_map(static fn (array $item): ?string => ! empty($item['id']) ? (string) $item['id'] : null, $items),
        ));

        // Hapus item yang tidak dipertahankan terlebih dahulu agar kode lama dapat dipakai ulang.
        $template->items()
            ->when($keptIds !== [], fn ($query): mixed => $query->whereNotIn('id', $keptIds))
            ->delete();

        /** @var array<string, BerkasChecklistItem> $existingById */
        $existingById = [];
        foreach ($items as $item) {
            if (empty($item['id'])) {
                continue;
            }

            $templateItem = $template->items()->whereKey($item['id'])->firstOrFail();
            $existingById[$templateItem->id] = $templateItem;

            // Ganti kode sementara bila kode berubah untuk mencegah tabrakan unique index saat swap/replace.
            if ($templateItem->kode !== $item['kode']) {
                activity()->withoutLogging(function () use ($templateItem): void {
                    $templateItem->update(['kode' => '__tmp_'.$templateItem->id]);
                });
            }
        }

        $order = 0;
        foreach ($items as $item) {
            $order++;
            $payload = [
                'kode' => $item['kode'],
                'nama' => $item['nama'],
                'wajib' => (bool) ($item['wajib'] ?? false),
                'urutan' => $item['urutan'] ?? $order,
            ];

            if (! empty($item['id'])) {
                $templateItem = $existingById[(string) $item['id']];

                // Jika kode target masih dipakai baris soft-deleted lain, arsipkan kode baris terhapus agar tidak menabrak unique index.
                $trashedConflict = $template->items()
                    ->onlyTrashed()
                    ->where('kode', $item['kode'])
                    ->whereKeyNot($templateItem->id)
                    ->first();

                if ($trashedConflict !== null) {
                    activity()->withoutLogging(function () use ($trashedConflict): void {
                        $trashedConflict->update([
                            'kode' => mb_substr($trashedConflict->kode, 0, 200).'__del_'.$trashedConflict->id,
                        ]);
                    });
                }

                $templateItem->update($payload);

                continue;
            }

            $trashedItem = $template->items()
                ->withTrashed()
                ->where('kode', $item['kode'])
                ->first();

            if ($trashedItem !== null) {
                if ($trashedItem->trashed()) {
                    $trashedItem->restore();
                }

                $trashedItem->update($payload);

                continue;
            }

            $template->items()->create($payload);
        }
    }

    private function cannotDeleteResponse(): RedirectResponse
    {
        return redirect()
            ->route('admin.checklist-template.index')
            ->with('error', 'Template checklist tidak dapat dihapus karena sudah digunakan.');
    }
}
