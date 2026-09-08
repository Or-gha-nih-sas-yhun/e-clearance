<?php

namespace App\Support;

/**
 * How many rows a Main Admin listing shows at once.
 *
 * Every listing offers the same choices from the same place — the pager under
 * its table ({@see resources/views/components/main-admin/table-pagination.blade.php})
 * — so the control means the same thing everywhere. Before this, four pages
 * accepted a `limit` of 10/25/50/100 and only one of them actually showed the
 * dropdown; the rest were fixed at 25 with no way to change it.
 *
 * The value arrives from a query string, so it is never trusted: anything that
 * is not one of the offered sizes falls back to {@see DEFAULT_SIZE} rather than
 * letting a URL ask the database for every row at once.
 */
final class ListPageSize
{
    /** @var list<int> */
    public const OPTIONS = [10, 20, 30, 40, 50];

    public const DEFAULT_SIZE = 10;

    public static function from(mixed $requested): int
    {
        $size = filter_var($requested, FILTER_VALIDATE_INT);

        return in_array($size, self::OPTIONS, true) ? $size : self::DEFAULT_SIZE;
    }
}
