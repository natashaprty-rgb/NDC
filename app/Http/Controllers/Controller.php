<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Apply ?sort=column&dir=asc|desc to a query, restricted to an allow-list
     * of columns so user input never reaches the SQL unchecked.
     *
     * @param  list<string>  $allowed
     */
    protected function applySort(Builder $query, Request $request, array $allowed, string $default, string $defaultDir = 'desc'): Builder
    {
        $sort = in_array($request->query('sort'), $allowed, true) ? $request->query('sort') : $default;
        $dir = in_array($request->query('dir'), ['asc', 'desc'], true) ? $request->query('dir') : $defaultDir;

        return $query->orderBy($query->getModel()->qualifyColumn($sort), $dir);
    }

    /**
     * Rows per page from ?per_page, bounded.
     */
    protected function perPage(Request $request, int $default = 20): int
    {
        $value = (int) $request->query('per_page', $default);

        return in_array($value, [10, 20, 50, 100], true) ? $value : $default;
    }
}
