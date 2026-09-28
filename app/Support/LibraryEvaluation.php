<?php

namespace App\Support;

use App\Models\AdminPersonnel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class LibraryEvaluation
{
    public const RATINGS = [
        5 => 'Strongly Agree',
        4 => 'Agree',
        3 => 'Neutral',
        2 => 'Disagree',
        1 => 'Strongly Disagree',
    ];

    public static function tables(string $scope = 'library'): array
    {
        return match ($scope) {
            'library' => ['library_evaluations', 'library_evaluation_responses'],
            'guidance' => ['guidance_evaluations', 'guidance_evaluation_responses'],
        };
    }

    public static function canManage(?object $account, string $scope = 'library'): bool
    {
        return $account instanceof AdminPersonnel
            && strtolower(trim((string) $account->role)) === $scope;
    }

    public static function available(string $scope = 'library'): bool
    {
        [$forms, $responses] = self::tables($scope);

        return Schema::hasTable($forms)
            && Schema::hasTable($responses)
            && Schema::hasColumns($forms, ['singleton', 'revision', 'sections']);
    }

    /** The single managed form, whether it is a draft or published. */
    public static function form(string $scope = 'library'): ?object
    {
        [$forms] = self::tables($scope);
        if (! Schema::hasTable($forms)) {
            return null;
        }

        return DB::table($forms)
            ->when(Schema::hasColumn($forms, 'singleton'), fn ($query) => $query->where('singleton', 1))
            ->orderByDesc('published_at')->orderByDesc('id')->first();
    }

    public static function current(string $scope = 'library'): ?object
    {
        $form = self::form($scope);

        return $form?->published_at ? $form : null;
    }

    public static function response(int $evaluationId, string $studentId, string $scope = 'library'): ?object
    {
        [, $responses] = self::tables($scope);

        return Schema::hasTable($responses)
            ? DB::table($responses)
                ->where('evaluation_id', $evaluationId)->where('student_id', $studentId)->first()
            : null;
    }

    public static function submissionAllowed(string $studentId, string $scope = 'library'): bool
    {
        $current = self::current($scope);

        return $current === null || self::response($current->id, $studentId, $scope) !== null;
    }

    public static function completions(?object $evaluation, array $studentIds, string $scope = 'library'): Collection
    {
        [, $responses] = self::tables($scope);

        return $evaluation && Schema::hasTable($responses)
            ? DB::table($responses)->where('evaluation_id', $evaluation->id)
                ->whereIn('student_id', $studentIds)->pluck('completed_at', 'student_id')
            : collect();
    }

    public static function questions(object $evaluation): array
    {
        return json_decode($evaluation->questions, true, 512, JSON_THROW_ON_ERROR);
    }

    public static function sectionMetadata(object $evaluation): array
    {
        return ! empty($evaluation->sections)
            ? json_decode($evaluation->sections, true, 512, JSON_THROW_ON_ERROR)
            : [['title' => '', 'description' => '', 'count' => count(self::questions($evaluation))]];
    }

    /** Keep the original question indexes so saved answers stay attached to their questions. */
    public static function sections(object $evaluation): array
    {
        $questions = self::questions($evaluation);
        $offset = 0;
        $sections = [];
        foreach (self::sectionMetadata($evaluation) as $section) {
            $sections[] = $section + ['offset' => $offset, 'questions' => array_slice($questions, $offset, $section['count'], true)];
            $offset += $section['count'];
        }

        return $sections;
    }
}
