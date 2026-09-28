<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

final class LibraryEvaluationSummary
{
    public static function build(object $evaluation, string $scope = 'library'): array
    {
        [, $responseTable] = LibraryEvaluation::tables($scope);
        $students = DB::table('student_account as students')
            ->leftJoin($responseTable.' as responses', function ($join) use ($evaluation) {
                $join->on('responses.student_id', '=', 'students.student_id')->where('responses.evaluation_id', $evaluation->id);
            });
        $programs = (clone $students)->select('students.program')
            ->selectRaw('COUNT(*) as total, COUNT(responses.id) as completed')
            ->groupBy('students.program')->orderBy('students.program')->get()->map(fn ($program) => [
                'name' => trim((string) $program->program) ?: 'Unspecified program',
                'total' => (int) $program->total,
                'pending' => (int) $program->total - (int) $program->completed,
            ])->all();
        $total = array_sum(array_column($programs, 'total'));
        $pending = array_sum(array_column($programs, 'pending'));
        $counts = array_fill_keys(array_keys(LibraryEvaluation::RATINGS), 0);
        $questions = array_map(fn ($question) => ['text' => $question, 'sum' => 0, 'count' => 0, 'average' => null], LibraryEvaluation::questions($evaluation));
        foreach ((clone $students)->whereNotNull('responses.id')->select('responses.ratings')->cursor() as $response) {
            foreach (json_decode($response->ratings, true, 512, JSON_THROW_ON_ERROR) as $index => $rating) {
                if (! isset($questions[$index]) || ! isset($counts[$rating])) {
                    continue;
                }
                $counts[$rating]++;
                $questions[$index]['sum'] += $rating;
                $questions[$index]['count']++;
            }
        }
        foreach ($questions as &$question) {
            $question['average'] = $question['count'] ? round($question['sum'] / $question['count'], 2) : null;
        }
        unset($question);
        $answerCount = array_sum($counts);

        return [
            'total' => $total, 'completed' => $total - $pending, 'pending' => $pending,
            'completionPercent' => $total ? round(($total - $pending) / $total * 100, 1) : 0,
            'average' => $answerCount ? round(array_sum(array_column($questions, 'sum')) / $answerCount, 2) : null,
            'answerCount' => $answerCount, 'ratingCounts' => $counts, 'questions' => $questions, 'programs' => $programs,
        ];
    }
}
