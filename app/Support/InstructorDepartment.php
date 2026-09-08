<?php

namespace App\Support;

/**
 * The departments an instructor account may belong to.
 *
 * BSED and BEED are two degree programs but one faculty — the College of
 * Education — so the instructor dropdowns offer the college rather than the
 * two programs. Students still enrol in BSED or BEED individually and every
 * other program list is unchanged; only `instructor_account.department` is
 * merged. Nothing scopes on that column (an instructor reaches students
 * through `instructor_assignment`, never through their department), so the
 * merge moves a label and not who sees whom.
 *
 * Rows written before the merge still read 'BSED' or 'BEED', and deploys never
 * run `artisan migrate`, so those values are folded in code instead of being
 * rewritten: {@see canonical()} maps them onto the college for display and
 * before every write, and {@see storedValues()} lets a filter on the college
 * still find them.
 */
final class InstructorDepartment
{
    public const COLLEGE_OF_EDUCATION = 'College of Education';

    /** What the dropdowns offer, in display order. */
    public const OPTIONS = ['BSIT', self::COLLEGE_OF_EDUCATION, 'BSBA', 'BSHM'];

    /** Pre-merge values that now mean College of Education. */
    public const EDUCATION_LEGACY = ['BSED', 'BEED'];

    /** Every value a stored department may legitimately hold, for validation. */
    public static function accepted(): array
    {
        return array_merge(self::OPTIONS, self::EDUCATION_LEGACY);
    }

    /** The department a stored value names, folding the two education programs. */
    public static function canonical(mixed $value): string
    {
        $value = trim((string) $value);

        foreach (self::EDUCATION_LEGACY as $legacy) {
            if (strcasecmp($value, $legacy) === 0) {
                return self::COLLEGE_OF_EDUCATION;
            }
        }

        foreach (self::OPTIONS as $option) {
            if (strcasecmp($value, $option) === 0) {
                return $option;
            }
        }

        return $value;
    }

    /** Every stored value a filter on this department has to match. */
    public static function storedValues(mixed $department): array
    {
        $canonical = self::canonical($department);

        return $canonical === self::COLLEGE_OF_EDUCATION
            ? array_merge([$canonical], self::EDUCATION_LEGACY)
            : [$canonical];
    }

    /** A class-name-safe form of a department, for the instructor portal theme. */
    public static function slug(mixed $department): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower(self::canonical($department)));

        return trim((string) $slug, '-') ?: 'bsit';
    }
}
