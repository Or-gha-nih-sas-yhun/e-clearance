<?php

namespace App\Http\Controllers;

use App\Models\SubjectCode;
use App\Support\ListPageSize;
use App\Support\RecordPurge;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SubjectCodeController extends Controller
{
    private array $programs = ['BSIT', 'BSBA', 'BSHM', 'BSED', 'BEED'];

    private array $semesters = ['1st Semester', '2nd Semester', 'Summer', 'Bridging'];

    public function index(Request $request)
    {
        $query = SubjectCode::query();
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('subject_code', 'like', "%{$request->search}%")
                    ->orWhere('subject_description', 'like', "%{$request->search}%");
            });
        }
        if ($request->year_level) {
            $query->where('year_level', $request->year_level);
        }
        if ($request->semester) {
            $query->where('semester', $request->semester);
        }
        if ($request->filled('department') && in_array($request->department, $this->programs, true)) {
            $query->whereRaw('FIND_IN_SET(?, program)', [$request->department]);
        }

        $order = in_array($request->order, ['ASC', 'DESC']) ? $request->order : 'DESC';
        $limit = ListPageSize::from($request->limit);
        $subjects = $query->orderBy('subject_id', $order)->paginate($limit)->withQueryString();
        $programs = $this->programs;
        $semesters = $this->semesters;

        return view('mainAdmin.subject-codes.index', compact('subjects', 'programs', 'semesters'));
    }

    public function store(Request $request)
    {
        $this->normalizeSubjectInput($request);
        $program = $this->programString($request);

        $data = $request->validate([
            'subject_code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('subject_codes', 'subject_code')
                    ->where('year_level', $request->input('year_level'))
                    ->where('program', $program)
                    ->where('semester', $request->input('semester')),
            ],
            'subject_description' => ['required', 'string', 'max:500'],
            'year_level' => 'required|integer|in:1,2,3,4',
            'program' => ['required', 'array', 'min:1', 'max:5'],
            'program.*' => ['required', 'string', 'distinct', Rule::in($this->programs)],
            'semester' => ['required', Rule::in($this->semesters)],
        ]);
        $data['program'] = implode(',', $data['program']);
        SubjectCode::create($data);

        return redirect()->route('subjects.index')->with('flash', ['type' => 'success', 'message' => 'Subject added.']);
    }

    public function update(Request $request, $id)
    {
        $subject = SubjectCode::findOrFail($id);
        $this->normalizeSubjectInput($request);
        $program = $this->programString($request);

        $data = $request->validate([
            'subject_code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('subject_codes', 'subject_code')
                    ->where('year_level', $request->input('year_level'))
                    ->where('program', $program)
                    ->where('semester', $request->input('semester'))
                    ->ignore($subject->subject_id, 'subject_id'),
            ],
            'subject_description' => ['required', 'string', 'max:500'],
            'year_level' => 'required|integer|in:1,2,3,4',
            'program' => ['required', 'array', 'min:1', 'max:5'],
            'program.*' => ['required', 'string', 'distinct', Rule::in($this->programs)],
            'semester' => ['required', Rule::in($this->semesters)],
        ]);
        $data['program'] = implode(',', $data['program']);
        $subject->update($data);

        return redirect()->route('subjects.index')->with('flash', ['type' => 'success', 'message' => 'Subject updated.']);
    }

    private function normalizeSubjectInput(Request $request): void
    {
        $programs = $request->input('program', []);
        if (is_array($programs)) {
            sort($programs);
        }

        $request->merge([
            'program' => $programs,
        ]);

        if ($request->has('subject_code')) {
            $request->merge([
                'subject_code' => strtoupper(trim((string) $request->input('subject_code'))),
            ]);
        }
    }

    private function programString(Request $request): string
    {
        $programs = $request->input('program', []);

        return is_array($programs) ? implode(',', $programs) : '';
    }

    public function destroy($id)
    {
        $subject = SubjectCode::find($id);

        if (! $subject) {
            return redirect()->route('subjects.index')->with('flash', ['type' => 'error', 'message' => 'That subject no longer exists.']);
        }

        DB::transaction(function () use ($subject): void {
            RecordPurge::subject($subject->getKey());
            $subject->delete();
        });

        return redirect()->route('subjects.index')->with('flash', ['type' => 'success', 'message' => 'Subject and all related details deleted.']);
    }

    // AJAX — replaces load_subjects.php
    public function getSubjects(Request $request)
    {
        $data = $request->validate([
            'program' => ['required', Rule::in($this->programs)],
            'year' => ['required', 'integer', 'in:1,2,3,4'],
        ]);

        $subjects = SubjectCode::where('year_level', $data['year'])
            ->where(function ($query) use ($data) {
                $program = $data['program'];
                $query->where('program', $program)
                    ->orWhere('program', 'like', $program.',%')
                    ->orWhere('program', 'like', '%,'.$program)
                    ->orWhere('program', 'like', '%,'.$program.',%');
            })->orderBy('subject_code')->get(['subject_id', 'subject_code', 'subject_description']);

        return response()->json($subjects);
    }
}
