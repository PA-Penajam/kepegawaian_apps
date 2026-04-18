<?php

namespace App\Services\PengajuanPerubahanData;

class PengajuanPerubahanDataDiffService
{
    /**
     * Membuat daftar diff dari dua array payload.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<int, array{field: string, label: string, before: mixed, after: mixed, change_type: string}>
     */
    public function make(array $before, array $after): array
    {
        $keys = array_unique([...array_keys($before), ...array_keys($after)]);

        return collect($keys)->map(fn (string $key) => [
            'field' => $key,
            'label' => str($key)->replace('_', ' ')->title()->value(),
            'before' => $before[$key] ?? null,
            'after' => $after[$key] ?? null,
            'change_type' => ! array_key_exists($key, $before)
                ? 'added'
                : (! array_key_exists($key, $after) ? 'removed' : (($before[$key] ?? null) === ($after[$key] ?? null) ? 'unchanged' : 'updated')),
        ])
            ->filter(fn (array $item) => $item['change_type'] !== 'unchanged')
            ->values()
            ->all();
    }
}
