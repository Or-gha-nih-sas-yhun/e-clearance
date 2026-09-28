<?php

namespace App\Http\Controllers\Office;

use App\Http\Controllers\Controller;
use App\Support\LibraryEvaluation;
use App\Support\LibraryEvaluationSummary;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LibraryEvaluationController extends Controller
{
    protected const SCOPE = 'library';

    private function authorizeLibrary(): object
    {
        $office = Auth::guard('office')->user();
        abort_unless(LibraryEvaluation::canManage($office, static::SCOPE), 403);

        return $office;
    }

    public function index(Request $request)
    {
        $office = $this->authorizeLibrary();
        $available = LibraryEvaluation::available(static::SCOPE);

        return DB::transaction(function () use ($request, $office, $available) {
            $selected = $available ? DB::table($this->evaluationTable())->where('singleton', 1)->sharedLock()->first() : null;
            $summary = null;
            $students = null;
            $completedCount = 0;
            $totalStudents = 0;

            if ($available && $selected?->published_at) {
                $query = DB::table('student_account as students')
                    ->leftJoin($this->responseTable().' as responses', function ($join) use ($selected) {
                        $join->on('responses.student_id', '=', 'students.student_id')
                            ->where('responses.evaluation_id', $selected->id);
                    });
                $summary = LibraryEvaluationSummary::build($selected, static::SCOPE);
                $totalStudents = $summary['total'];
                $completedCount = $summary['completed'];
                $search = mb_substr(trim((string) $request->query('search', '')), 0, 100);
                $students = $query
                    ->when($search !== '', fn ($q) => $q->where(fn ($names) => $names
                        ->where('students.student_id', 'like', "%{$search}%")
                        ->orWhere('students.firstname', 'like', "%{$search}%")
                        ->orWhere('students.lastname', 'like', "%{$search}%")))
                    ->when($request->query('completion') === 'completed', fn ($q) => $q->whereNotNull('responses.id'))
                    ->when($request->query('completion') === 'pending', fn ($q) => $q->whereNull('responses.id'))
                    ->select('students.student_id', 'students.firstname', 'students.lastname', 'students.program',
                        'students.year_level', 'students.section', 'responses.id as response_id', 'responses.completed_at')
                    ->orderBy('students.lastname')->orderBy('students.firstname')->orderBy('students.student_id')
                    ->paginate(15)->withQueryString();
            }

            return view('office.library-evaluations.index', compact(
                'office', 'available', 'selected', 'students', 'completedCount', 'totalStudents', 'summary',
            ))->with($this->viewContext());
        });
    }

    public function create()
    {
        $office = $this->authorizeLibrary();
        abort_unless(LibraryEvaluation::available(static::SCOPE), 503, ucfirst(static::SCOPE).' evaluations are not available yet.');

        if ($existing = LibraryEvaluation::form(static::SCOPE)) {
            return redirect()->route($this->route('edit'), $existing->id);
        }

        return view('office.library-evaluations.form', ['office' => $office, 'evaluation' => null])->with($this->viewContext());
    }

    public function edit(int $evaluation)
    {
        $office = $this->authorizeLibrary();
        $evaluation = $this->findEvaluation($evaluation);

        return view('office.library-evaluations.form', compact('office', 'evaluation'))->with($this->viewContext());
    }

    public function store(Request $request)
    {
        $office = $this->authorizeLibrary();
        abort_unless(LibraryEvaluation::available(static::SCOPE), 503, ucfirst(static::SCOPE).' evaluations are not available yet.');
        $data = $this->validatedForm($request);
        if (LibraryEvaluation::form(static::SCOPE)) {
            throw ValidationException::withMessages(['evaluation' => 'The '.(static::SCOPE === 'guidance' ? 'guidance office' : 'library').' already has an evaluation. Edit or delete the existing form first.']);
        }
        try {
            $id = DB::table($this->evaluationTable())->insertGetId($data + [
                'singleton' => 1, 'revision' => 1,
                'created_by' => $office->personnel_id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException $exception) {
            // The unique slot also prevents simultaneous creation in two tabs.
            throw ValidationException::withMessages(['evaluation' => 'The '.(static::SCOPE === 'guidance' ? 'guidance office' : 'library').' already has an evaluation. Edit or delete the existing form first.']);
        }

        return redirect()->route($this->route('index'), ['evaluation' => $id])
            ->with('flash', ['type' => 'success', 'message' => 'Evaluation draft saved. Review the questions, then publish it for students.']);
    }

    public function update(Request $request, int $evaluation)
    {
        $this->authorizeLibrary();
        $data = $this->validatedForm($request);
        $changed = DB::transaction(function () use ($request, $evaluation, $data) {
            $record = $this->findEvaluation($evaluation, true);
            $this->assertRevision($request, $record);
            $changed = $data['title'] !== $record->title
                || (string) $data['description'] !== (string) $record->description
                || json_decode($data['questions'], true) !== LibraryEvaluation::questions($record)
                || json_decode($data['sections'], true) !== LibraryEvaluation::sectionMetadata($record);
            if (! $changed) {
                return false;
            }

            DB::table($this->responseTable())->where('evaluation_id', $evaluation)->delete();
            DB::table($this->evaluationTable())->where('id', $evaluation)->update($data + [
                'revision' => $record->revision + 1, 'updated_at' => now(),
            ]);

            return true;
        });

        return redirect()->route($this->route('index'))
            ->with('flash', ['type' => 'success', 'message' => $changed
                ? 'Evaluation updated. All previous responses have been deleted. Students must answer the revised form.'
                : 'No changes were made. Existing responses have been kept.']);
    }

    public function publish(Request $request, int $evaluation)
    {
        $this->authorizeLibrary();
        DB::transaction(function () use ($request, $evaluation) {
            $record = $this->findEvaluation($evaluation, true);
            $this->assertRevision($request, $record);
            abort_if($record->published_at, 409, 'This evaluation has already been published.');
            DB::table($this->evaluationTable())->where('id', $evaluation)
                ->update(['published_at' => now()->format('Y-m-d H:i:s.u'), 'updated_at' => now()]);
        });

        return redirect()->route($this->route('index'))
            ->with('flash', ['type' => 'success', 'message' => 'Evaluation published. Students must complete it before submitting '.static::SCOPE.' clearance.']);
    }

    public function destroy(Request $request, int $evaluation)
    {
        $this->authorizeLibrary();
        DB::transaction(function () use ($request, $evaluation) {
            $record = $this->findEvaluation($evaluation, true);
            $this->assertRevision($request, $record);
            DB::table($this->responseTable())->where('evaluation_id', $evaluation)->delete();
            DB::table($this->evaluationTable())->where('id', $evaluation)->delete();
        });

        return redirect()->route($this->route('index'))
            ->with('flash', ['type' => 'success', 'message' => 'Evaluation and its responses deleted. You can now create a new form.']);
    }

    public function export(int $evaluation)
    {
        $this->authorizeLibrary();
        // Snapshot questions and answers under the same lock used by edits,
        // so a download cannot mix questions with answers from an earlier form.
        [$record, $responses] = DB::transaction(function () use ($evaluation) {
            $record = $this->findEvaluation($evaluation, true);
            $responses = DB::table($this->responseTable().' as responses')
                ->join('student_account as students', 'students.student_id', '=', 'responses.student_id')
                ->where('responses.evaluation_id', $evaluation)
                ->orderBy('responses.completed_at')->orderBy('responses.id')
                ->select('responses.*', 'students.firstname', 'students.lastname', 'students.program', 'students.year_level', 'students.section')
                ->get();

            return [$record, $responses];
        });

        return response()->streamDownload(function () use ($record, $responses) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            $questions = LibraryEvaluation::questions($record);
            $header = ['Timestamp', 'Student ID', 'Student name', 'Program', 'Year level', 'Section', 'Evaluation'];
            foreach (LibraryEvaluation::sections($record) as $section) {
                foreach ($section['questions'] as $index => $question) {
                    $header[] = 'Q'.($index + 1).'. '.($section['title'] !== '' ? $section['title'].' — ' : '').$question;
                }
            }
            $write = function (array $row) use ($output) {
                fputcsv($output, array_map([$this, 'csvValue'], $row), ',', '"', '', "\r\n");
            };
            $write($header);
            foreach ($responses as $response) {
                $row = [$response->completed_at, $response->student_id,
                    trim($response->firstname.' '.$response->lastname), $response->program,
                    $response->year_level, $response->section, $record->title];
                $ratings = json_decode($response->ratings, true, 512, JSON_THROW_ON_ERROR);
                foreach (array_keys($questions) as $index) {
                    $rating = $ratings[$index];
                    $row[] = $rating.' - '.LibraryEvaluation::RATINGS[$rating];
                }
                $write($row);
            }
            fclose($output);
        }, static::SCOPE.'-evaluation-responses-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvValue(mixed $value): string
    {
        $value = (string) $value;

        // Quoting a CSV cell alone does not prevent spreadsheet formulas.
        return preg_match('/^[\s\p{Z}]*[=+@-]|^[\t\r\n]/u', $value) ? "'".$value : $value;
    }

    private function assertRevision(Request $request, object $evaluation): void
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        if ((int) $data['revision'] !== (int) $evaluation->revision) {
            throw ValidationException::withMessages(['revision' => 'This evaluation has changed. Reload the page before making changes.']);
        }
    }

    public function showResponse(Request $request, int $response)
    {
        $office = $this->authorizeLibrary();
        abort_unless(LibraryEvaluation::available(static::SCOPE), 503);
        $response = DB::table($this->responseTable())->where('id', $response)->first();
        abort_unless($response, 404);
        [$evaluation, $response] = DB::transaction(function () use ($response) {
            $evaluation = $this->findEvaluation($response->evaluation_id, true);
            $response = DB::table($this->responseTable())->where('id', $response->id)->first();
            abort_unless($response, 404);

            return [$evaluation, $response];
        });
        $student = DB::table('student_account')->where('student_id', $response->student_id)->first();
        abort_unless($student, 404);

        if ($request->ajax()) {
            return view('office.library-evaluations.response-answers', compact('response', 'evaluation'));
        }

        return view('office.library-evaluations.response', compact('office', 'response', 'evaluation', 'student'))->with($this->viewContext());
    }

    private function findEvaluation(int $id, bool $lock = false): object
    {
        abort_unless(LibraryEvaluation::available(static::SCOPE), 503, ucfirst(static::SCOPE).' evaluations are not available yet.');
        $query = DB::table($this->evaluationTable())->where('id', $id)->where('singleton', 1);
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($record, 404);

        return $record;
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
        return 'office.'.static::SCOPE.'-evaluations.'.$action;
    }

    private function viewContext(): array
    {
        return [
            'scope' => static::SCOPE,
            'officeLabel' => ucfirst(static::SCOPE),
            'officeRouteBase' => 'office.'.static::SCOPE.'-evaluations',
        ];
    }

    private function validatedForm(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:3000'],
            'sections' => ['sometimes', 'required', 'array', 'min:1', 'max:10'],
            'sections.*' => ['required', 'array:title,description,questions'],
            'sections.*.title' => ['nullable', 'string', 'max:200'],
            'sections.*.description' => ['nullable', 'string', 'max:3000'],
            'sections.*.questions' => ['required', 'array', 'min:1', 'max:50'],
            'sections.*.questions.*' => ['required', 'string', 'max:500'],
            'questions' => ['required_without:sections'],
        ]);
        $sections = [];
        if (isset($data['sections'])) {
            $questions = [];
            foreach ($data['sections'] as $section) {
                $sectionQuestions = array_values($section['questions']);
                $sections[] = ['title' => trim($section['title'] ?? ''), 'description' => trim($section['description'] ?? ''), 'count' => count($sectionQuestions)];
                array_push($questions, ...$sectionQuestions);
            }
        } else {
            $questions = is_string($data['questions'])
                ? array_values(array_filter(array_map('trim', preg_split('/\R/u', $data['questions']) ?: []), fn ($question) => $question !== ''))
                : $data['questions'];
        }
        if (is_array($questions)) {
            $questions = array_values(array_map(fn ($question) => is_string($question) ? trim($question) : $question, $questions));
        }
        $validated = Validator::make(['questions' => $questions], [
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*' => ['required', 'string', 'max:500'],
        ], [
            'questions.*.required' => 'Enter a statement for every question.',
            'questions.*.max' => 'Each question must be 500 characters or fewer.',
        ])->validate();

        $sections = $sections ?: [['title' => '', 'description' => '', 'count' => count($validated['questions'])]];

        return ['title' => $data['title'], 'description' => $data['description'] ?? null,
            'questions' => json_encode($validated['questions'], JSON_THROW_ON_ERROR),
            'sections' => json_encode($sections, JSON_THROW_ON_ERROR)];
    }
}
