<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Helper portabel untuk pencarian SQL LIKE dengan escaping karakter wildcard
 * (% dan _) menggunakan klausa ESCAPE '!' agar identik di SQLite dan MySQL.
 */
class SqlLike
{
    /**
     * Meng-escape karakter wildcard LIKE agar dicocokkan sebagai karakter literal.
     */
    public static function escape(string $term, string $escapeChar = '!'): string
    {
        return str_replace(
            [$escapeChar, '%', '_'],
            [$escapeChar.$escapeChar, $escapeChar.'%', $escapeChar.'_'],
            $term
        );
    }

    /**
     * Menerapkan filter LIKE terkelompok pada satu atau beberapa kolom.
     *
     * @param  string|array<int, string>  $columns
     */
    public static function whereContains(Builder $query, string|array $columns, ?string $term): Builder
    {
        $trimmed = trim((string) $term);
        $columnList = is_array($columns) ? $columns : [$columns];

        if ($trimmed === '' || $columnList === []) {
            return $query;
        }

        $pattern = '%'.self::escape($trimmed).'%';
        $grammar = $query->getQuery()->getGrammar();

        return $query->where(function (Builder $builder) use ($columnList, $pattern, $grammar): void {
            foreach ($columnList as $column) {
                $wrapped = $grammar->wrap($column);
                $builder->orWhereRaw("{$wrapped} LIKE ? ESCAPE '!'", [$pattern]);
            }
        });
    }
}
