<?php

namespace App\Traits;

use App\Support\SqlLike;
use Illuminate\Database\Eloquent\Builder;

trait Filterable
{
    public function scopeSearch(Builder $query, ?string $search, array $columns): Builder
    {
        return SqlLike::whereContains($query, $columns, $search);
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
