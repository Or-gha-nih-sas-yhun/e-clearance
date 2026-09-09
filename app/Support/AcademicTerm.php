<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The term the college is currently running: a semester and an academic year.
 *
 * Main Admin sets it on the System Settings page. Its one functional effect is
 * on subject assignment — only subjects belonging to the active semester are
 * offered when assigning an instructor, or when an irregular student picks
 * their own subjects, so a new term cannot be built out of last term's
 * subjects. The academic year is a label: it identifies the term on the printed
 * clearance form and on the settings page.
 *
 * Two deliberate fallbacks, both because deploys never run `artisan migrate`:
 *
 * - Without the `system_settings` table, or with no semester saved yet,
 *   {@see filtersSubjects()} is false and **every** subject is offered. That is
 *   exactly how the system behaved before this setting existed, so an
 *   un-migrated database keeps working instead of offering an empty dropdown.
 * - The value is read at most once per request ({@see $cache}), because the
 *   subject filter is consulted from several screens.
 */
final class AcademicTerm
{
    /** The semesters a term can be in, matching `subject_codes.semester`. */
    public const SEMESTERS = ['1st Semester', '2nd Semester', 'Summer'];

    public const SEMESTER_KEY = 'active_semester';

    public const YEAR_KEY = 'academic_year';

    /** @var array<string, ?string>|null */
    private static ?array $cache = null;

    public static function available(): bool
    {
        return Schema::hasTable('system_settings');
    }

    public static function semester(): ?string
    {
        $semester = self::read(self::SEMESTER_KEY);

        return in_array($semester, self::SEMESTERS, true) ? $semester : null;
    }

    public static function academicYear(): ?string
    {
        return self::read(self::YEAR_KEY);
    }

    /**
     * Whether subject lists should be narrowed at all.
     *
     * False on a database with no settings table and on one where no semester
     * has been chosen yet — in both cases every subject stays available.
     */
    public static function filtersSubjects(): bool
    {
        return self::semester() !== null;
    }

    /** "1st Semester · A.Y. 2026-2027", or a plain note when nothing is set. */
    public static function label(): string
    {
        $semester = self::semester();
        $year = self::academicYear();

        if ($semester === null && $year === null) {
            return 'No term set';
        }

        return trim(($semester ?? 'Semester not set').($year ? ' · A.Y. '.$year : ''));
    }

    /** Narrow a subject query to the active semester, or leave it alone. */
    public static function scopeSubjects($query, string $column = 'semester')
    {
        $semester = self::semester();

        return $semester === null ? $query : $query->where($column, $semester);
    }

    /** Whether a subject belongs to the active term. True when nothing is set. */
    public static function includesSubject(?string $semester): bool
    {
        $active = self::semester();

        return $active === null || trim((string) $semester) === $active;
    }

    /**
     * Save the term. An empty academic year clears it rather than storing '',
     * so {@see label()} can tell "not set" from "set to nothing".
     */
    public static function save(?string $semester, ?string $academicYear): void
    {
        if (! self::available()) {
            return;
        }

        self::write(self::SEMESTER_KEY, in_array($semester, self::SEMESTERS, true) ? $semester : null);
        self::write(self::YEAR_KEY, ($academicYear = trim((string) $academicYear)) === '' ? null : $academicYear);

        self::$cache = null;
    }

    /** Drop the memoised values — for tests, which change the term mid-run. */
    public static function forget(): void
    {
        self::$cache = null;
    }

    private static function read(string $key): ?string
    {
        if (self::$cache === null) {
            self::$cache = self::available()
                ? DB::table('system_settings')->pluck('value', 'key')->all()
                : [];
        }

        $value = self::$cache[$key] ?? null;

        return ($value = trim((string) $value)) === '' ? null : $value;
    }

    private static function write(string $key, ?string $value): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now()],
        );
    }
}
