<?php

namespace App\Support;

/**
 * Canonical form of a section name, for comparison only.
 *
 * Section names are free text on every form and importer that writes them
 * (`'section' => 'string|max:50'`), so one section reaches the database as
 * "SOUTHEAST" from the Main Admin dropdowns and "South East" from a
 * hand-typed treasurer record. Every scope deciding which students a section
 * treasurer or an assigned instructor may act on compares sections, and exact
 * equality silently scopes that staff member away from their own students —
 * the clearance simply never appears, with no error to explain it.
 *
 * Comparing on a key fixes existing rows without rewriting anyone's data.
 * REPLACE() exists in both MySQL and SQLite, so {@see sql()} stays portable
 * between production and the test suite, and matches {@see of()} exactly.
 */
final class SectionKey
{
    /** Characters that carry no meaning in a section name. */
    private const NOISE = [' ', '-', '_'];

    public static function of(mixed $value): string
    {
        return str_replace(self::NOISE, '', strtolower(trim((string) $value)));
    }

    public static function matches(mixed $left, mixed $right): bool
    {
        $left = self::of($left);
        $right = self::of($right);

        return $left !== '' && $right !== '' && hash_equals($left, $right);
    }

    /**
     * SQL reducing a column or a `?` placeholder to the same key as {@see of()}.
     */
    public static function sql(string $expression): string
    {
        return "REPLACE(REPLACE(REPLACE(LOWER(TRIM({$expression})), ' ', ''), '-', ''), '_', '')";
    }
}
