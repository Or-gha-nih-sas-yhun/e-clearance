<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Support\LibraryEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LibraryEvaluationController extends Controller
{
    protected const SCOPE = 'library';

    public function index()
    {
        $student = Auth::guard('student')->user();
        $evaluation = LibraryEvaluation::current(static::SCOPE);
        $response = null;
        if ($evaluation) {
            abort_unless(LibraryEvaluation::available(static::SCOPE), 503, 'The evaluation is temporarily unavailable. Please try again later.');
            [$evaluation, $response] = DB::transaction(function () use ($student) {
                $evaluation = DB::table($this->evaluationTable())->where('singleton', 1)
                    ->whereNotNull('published_at')->sharedLock()->first();

                return [$evaluation, $evaluation ? LibraryEvaluation::response($evaluation->id, $student->student_id, static::SCOPE) : null];
            });
        }

        return view('student.library-evaluation', compact('student', 'evaluation', 'response'))->with([
            'scope' => static::SCOPE, 'officeLabel' => ucfirst(static::SCOPE),
        ]);
    }

    private function evaluationTable(): string
    {
        return LibraryEvaluation::tables(static::SCOPE)[0];
    }

    private function responseTable(): string
    {
        return LibraryEvaluation::tables(static::SCOPE)[1];
    }

    private function route(string $action): string
    {
        return 'student.'.static::SCOPE.'-evaluation.'.$action;
    }

    public function store(Request $request)
    {
        $request->validate([
            'evaluation_id' => ['required', 'integer', 'min:1'],
            'revision' => ['required', 'integer', 'min:1'],
        ]);
        $student = Auth::guard('student')->user();
        $evaluation = LibraryEvaluation::current(static::SCOPE);
        abort_if($evaluation && ! LibraryEvaluation::available(static::SCOPE), 503, 'The evaluation is temporarily unavailable. Please try again later.');
        if (! $evaluation || (string) $request->input('evaluation_id') !== (string) $evaluation->id
            || $request->integer('revision') !== (int) $evaluation->revision) {
            return redirect()->route($this->route('index'))->with('flash', [
                'type' => 'warning', 'message' => 'The required evaluation has changed. Please review the current form.',
            ]);
        }
        $questions = LibraryEvaluation::questions($evaluation);
        $rules = ['ratings' => ['required', 'array', 'size:'.count($questions)]];
        foreach ($questions as $index => $question) {
            $rules['ratings.'.$index] = ['required', 'integer', Rule::in(array_keys(LibraryEvaluation::RATINGS))];
        }
        $data = $request->validate($rules, [
            'ratings.required' => 'Please answer every question.',
            'ratings.size' => 'Please answer every question on this evaluation.',
            'ratings.*.required' => 'Please select a rating for every question.',
            'ratings.*.in' => 'Ratings must be between 1 and 5.',
        ]);
        $ratings = [];
        foreach (array_keys($questions) as $index) {
            $ratings[] = (int) $data['ratings'][$index];
        }

        DB::transaction(function () use ($evaluation, $student, $ratings) {
            $current = DB::table($this->evaluationTable())->where('singleton', 1)
                ->whereNotNull('published_at')->lockForUpdate()->first();
            if ($current?->id !== $evaluation->id || (int) $current->revision !== (int) $evaluation->revision) {
                throw ValidationException::withMessages(['evaluation_id' => 'The evaluation changed. Reload this page to answer the current form.']);
            }
            // One complete response per student and form, even if a browser retries.
            DB::table($this->responseTable())->insertOrIgnore([
                'evaluation_id' => $evaluation->id, 'student_id' => $student->student_id,
                'ratings' => json_encode($ratings, JSON_THROW_ON_ERROR), 'completed_at' => now(),
            ]);
        });

        return redirect()->route('student.clearance-updates')->with('flash', [
            'type' => 'success', 'message' => ucfirst(static::SCOPE).' evaluation completed. You can now submit your '.static::SCOPE.' clearance request.',
        ]);
    }
}
