<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    public function scopeSearch(Builder $query, ?string $search, array $columns): Builder
    {
        $term = trim((string) $search);

        if ($term === '' || $columns === []) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($columns, $term): void {
            foreach ($columns as $column) {
                $builder->orWhere($column, 'like', "%{$term}%");
            }
        });
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $query->where($column, $value);
        }

        return $query;
    }

    public function scopeSorted(Builder $query, ?string $sortBy, string $sortDir = 'asc'): Builder
    {
        if ($sortBy === null || $sortBy === '') {
            return $query;
        }

        $direction = strtolower($sortDir) === 'desc' ? 'desc' : 'asc';

        return $query->orderBy($sortBy, $direction);
    }
}
