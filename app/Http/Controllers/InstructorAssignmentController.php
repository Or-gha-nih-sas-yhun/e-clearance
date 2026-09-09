<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Models\InstructorAssignment;
use App\Models\ProgramSection;
use App\Models\SubjectCode;
use App\Support\AcademicTerm;
use App\Support\InstructorDepartment;
use App\Support\ListPageSize;
use App\Support\RecordPurge;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Subject assignments, Main Admin.
 *
 * One browse screen and one instructor screen, both on `assignments.index` and
 * both addressed by query string, so any view can be linked and returned to.
 *
 * The browse screen always shows the whole assignment list with its
 * instructor / year / section filters. Picking a department is just another filter on that
 * list: it narrows the table to the instructors of that faculty and, because it
 * has narrowed to a faculty, also shows those instructors as cards. Clicking a
 * card (`&instructor=ID`) opens that instructor's own screen, where the add
 * form already knows who it is assigning to.
 *
 * The departments are the *instructor's* faculty ({@see InstructorDepartment}),
 * where BSED and BEED are one College of Education. An assignment's `program`
 * is a *student* program and stays one of the five — a College of Education
 * instructor is assigned to BSED or BEED sections individually.
 */
class InstructorAssignmentController extends Controller
{
    /** Instructors whose department is not one of the four faculties. */
    private const UNASSIGNED = 'Unassigned';

    /** Student programs an assignment can target. Not the instructor faculties. */
    private const PROGRAMS = ['BSIT', 'BSBA', 'BSHM', 'BSED', 'BEED'];

    public function index(Request $request)
    {
        $loads = $this->assignmentLoads();
        $faculty = Instructor::orderBy('lastname')->orderBy('firstname')->get()
            ->groupBy(fn (Instructor $instructor) => $this->facultyOf($instructor));

        $department = $this->requestedDepartment($request);
        $search = trim((string) $request->query('search', ''));

        if ($department !== null) {
            $instructor = $this->requestedInstructor($request, $faculty->get($department, collect()));

            if ($instructor !== null) {
                return $this->instructorScreen($department, $instructor);
            }
        }

        $roster = $this->matching(
            $department === null ? $faculty->flatten(1) : $faculty->get($department, collect()),
            $search,
        );

        return view('mainAdmin.assignments.index', [
            'stage' => 'browse',
            'department' => $department,
            'search' => $search,
            'departments' => $this->departmentTabs($faculty, $loads),
            'roster' => $this->rosterCards($roster, $loads),
            // The roster only earns its panel once the list has been narrowed to
            // a faculty or a name; otherwise it is just every instructor again.
            'showRoster' => $department !== null || $search !== '',
            'assignments' => $this->assignmentList($request, $department, $search, $roster),
            'programs' => self::PROGRAMS,
            'instructors' => $this->allInstructors(),
            'subjects' => $this->allSubjects(),
            'sections' => $this->allSections(),
            'activeSemester' => AcademicTerm::semester(),
            'termLabel' => AcademicTerm::label(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'instructor_id' => 'required|string|max:50|exists:instructor_account,instructor_id',
            'subject_id' => 'required|integer|min:1|exists:subject_codes,subject_id',
            'program' => 'required|in:BSIT,BSBA,BSHM,BSED,BEED',
            'year_level' => 'required|integer|in:1,2,3,4',
            'sections' => 'required|array|min:1|max:20',
            'sections.*' => 'required|string|max:50|distinct',
        ]);
        $this->ensureSubjectScope($data, enforceTerm: true);

        $sections = collect($data['sections'])->map(fn ($section) => strtoupper(trim($section)))->unique()->values();
        $this->ensureManagedSections($data, $sections);
        $created = 0;

        DB::transaction(function () use ($data, $sections, &$created): void {
            foreach ($sections as $section) {
                $exists = InstructorAssignment::where([
                    'subject_id' => $data['subject_id'], 'program' => $data['program'],
                    'year_level' => $data['year_level'], 'section' => $section,
                ])->exists();
                if (! $exists) {
                    InstructorAssignment::create([
                        'instructor_id' => $data['instructor_id'],
                        'subject_id' => $data['subject_id'],
                        'program' => $data['program'],
                        'year_level' => $data['year_level'],
                        'section' => $section,
                    ]);
                    $created++;
                }
            }
        });

        $message = $created ? "Assignment created for {$created} section(s)." : 'No new assignments were created; selected sections may already be assigned.';

        return $this->backToOrigin(['type' => $created ? 'success' : 'warning', 'message' => $message]);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'instructor_id' => 'required|string|max:50|exists:instructor_account,instructor_id',
            'subject_id' => 'required|integer|min:1|exists:subject_codes,subject_id',
            'program' => 'required|in:BSIT,BSBA,BSHM,BSED,BEED',
            'year_level' => 'required|integer|in:1,2,3,4',
            'sections' => 'required|array|min:1|max:20',
            'sections.*' => 'required|string|max:50|distinct',
        ]);
        $assignment = InstructorAssignment::findOrFail($id);
        $this->ensureSubjectScope($data, enforceTerm: false);
        $sections = collect($data['sections'])->map(fn ($section) => strtoupper(trim($section)))->unique()->values();
        $this->ensureManagedSections($data, $sections);

        $conflict = InstructorAssignment::where('subject_id', $data['subject_id'])
            ->where('program', $data['program'])
            ->where('year_level', $data['year_level'])
            ->whereIn('section', $sections)
            ->whereKeyNot($assignment->getKey())
            ->exists();
        if ($conflict) {
            throw ValidationException::withMessages([
                'sections' => 'One or more selected sections already have an instructor for this subject.',
            ]);
        }

        $created = 0;

        DB::transaction(function () use ($assignment, $data, $sections, &$created): void {
            $assignment->delete();
            foreach ($sections as $section) {
                InstructorAssignment::create([
                    'instructor_id' => $data['instructor_id'], 'subject_id' => $data['subject_id'],
                    'program' => $data['program'], 'year_level' => $data['year_level'], 'section' => $section,
                ]);
                $created++;
            }
        });

        return $this->backToOrigin([
            'type' => $created ? 'success' : 'warning',
            'message' => $created ? "Assignment updated for {$created} section(s)." : 'No assignments were created because the selected sections are already assigned.',
        ]);
    }

    public function destroy($id)
    {
        $assignment = InstructorAssignment::find($id);

        if (! $assignment) {
            return $this->backToOrigin(['type' => 'error', 'message' => 'That assignment no longer exists.']);
        }

        DB::transaction(function () use ($assignment): void {
            RecordPurge::instructorAssignment($assignment);
            $assignment->delete();
        });

        return $this->backToOrigin(['type' => 'success', 'message' => 'Assignment and all related details deleted.']);
    }

    /** One instructor's own screen: their subjects, and a form that knows them. */
    private function instructorScreen(string $department, Instructor $instructor)
    {
        return view('mainAdmin.assignments.index', [
            'stage' => 'instructor',
            'department' => $department,
            'instructor' => $instructor,
            'assignments' => InstructorAssignment::with('subject')
                ->where('instructor_id', $instructor->instructor_id)
                ->orderBy('program')->orderBy('year_level')->orderBy('section')
                ->get(),
            'programs' => self::PROGRAMS,
            'instructors' => $this->allInstructors(),
            'subjects' => $this->allSubjects(),
            'sections' => $this->allSections(),
            'activeSemester' => AcademicTerm::semester(),
            'termLabel' => AcademicTerm::label(),
        ]);
    }

    /**
     * The assignment table behind the browse screen.
     *
     * Department and instructor search narrow it through the roster that the
     * cards are drawn from, so the table and the cards can never disagree. With
     * neither applied the table is left unconstrained on purpose: a row whose
     * instructor account was deleted still shows up, rather than quietly
     * vanishing from every view.
     *
     * @param  Collection<int, Instructor>  $roster
     */
    private function assignmentList(Request $request, ?string $department, string $search, Collection $roster)
    {
        $query = InstructorAssignment::with(['instructor', 'subject']);

        if ($department !== null || $search !== '') {
            $query->whereIn('instructor_id', $roster->pluck('instructor_id')->all());
        }
        if ($request->year_level) {
            $query->where('year_level', $request->year_level);
        }
        if ($request->section) {
            $query->where('section', 'like', "%{$request->section}%");
        }

        return $query->orderByDesc('assignment_id')->paginate(ListPageSize::from($request->limit))->withQueryString();
    }

    /** How many assignments each instructor carries, keyed by instructor_id. */
    private function assignmentLoads(): Collection
    {
        return DB::table('instructor_assignment')
            ->selectRaw('instructor_id, COUNT(*) as total')
            ->groupBy('instructor_id')
            ->pluck('total', 'instructor_id');
    }

    /**
     * The faculty an instructor is filed under. Anyone whose department is not
     * one of the four lands in a visible "Unassigned" tab rather than dropping
     * out of the page entirely.
     */
    private function facultyOf(Instructor $instructor): string
    {
        $department = $instructor->department_label;

        return in_array($department, InstructorDepartment::OPTIONS, true) ? $department : self::UNASSIGNED;
    }

    /**
     * The department filter buttons, in display order, each with its counts.
     *
     * @param  Collection<string, Collection<int, Instructor>>  $faculty
     * @return list<array{name: string, instructors: int, assignments: int, icon: string}>
     */
    private function departmentTabs(Collection $faculty, Collection $loads): array
    {
        $icons = [
            'BSIT' => 'bi bi-cpu',
            InstructorDepartment::COLLEGE_OF_EDUCATION => 'bi bi-mortarboard',
            'BSBA' => 'bi bi-briefcase',
            'BSHM' => 'bi bi-cup-hot',
            self::UNASSIGNED => 'bi bi-question-circle',
        ];

        $tabs = [];
        foreach ([...InstructorDepartment::OPTIONS, self::UNASSIGNED] as $name) {
            /** @var Collection<int, Instructor> $roster */
            $roster = $faculty->get($name, collect());

            // The catch-all only earns a button when somebody is actually in it.
            if ($name === self::UNASSIGNED && $roster->isEmpty()) {
                continue;
            }

            $tabs[] = [
                'name' => $name,
                'instructors' => $roster->count(),
                'assignments' => (int) $roster->sum(fn (Instructor $instructor) => $loads[$instructor->instructor_id] ?? 0),
                'icon' => $icons[$name],
            ];
        }

        return $tabs;
    }

    /**
     * @param  Collection<int, Instructor>  $roster
     * @return Collection<int, Instructor>
     */
    private function matching(Collection $roster, string $search): Collection
    {
        if ($search === '') {
            return $roster;
        }

        $needle = strtolower($search);

        return $roster->filter(function (Instructor $instructor) use ($needle) {
            $haystack = strtolower("{$instructor->instructor_id} {$instructor->firstname} {$instructor->lastname} {$instructor->email}");

            return str_contains($haystack, $needle);
        })->values();
    }

    /**
     * Each card carries its own department so a card found by searching across
     * every faculty still links to the right instructor screen.
     *
     * @param  Collection<int, Instructor>  $roster
     * @return list<array{id: string, name: string, email: string, department: string, position: ?string, assignments: int}>
     */
    private function rosterCards(Collection $roster, Collection $loads): array
    {
        $tracksPosition = Instructor::tracksEmploymentStatus();

        return $roster->map(fn (Instructor $instructor) => [
            'id' => (string) $instructor->instructor_id,
            'name' => trim("{$instructor->firstname} {$instructor->lastname}"),
            'email' => (string) $instructor->email,
            'department' => $this->facultyOf($instructor),
            'position' => $tracksPosition ? $instructor->employment_status : null,
            'assignments' => (int) ($loads[$instructor->instructor_id] ?? 0),
        ])->values()->all();
    }

    /** The department asked for, or null when the request names none we know. */
    private function requestedDepartment(Request $request): ?string
    {
        $requested = trim((string) $request->query('department', ''));

        if ($requested === '') {
            return null;
        }

        if (strcasecmp($requested, self::UNASSIGNED) === 0) {
            return self::UNASSIGNED;
        }

        $canonical = InstructorDepartment::canonical($requested);

        return in_array($canonical, InstructorDepartment::OPTIONS, true) ? $canonical : null;
    }

    /**
     * The instructor asked for, but only if they really are in this department —
     * otherwise the breadcrumb would claim a faculty the instructor is not in.
     *
     * @param  Collection<int, Instructor>  $roster
     */
    private function requestedInstructor(Request $request, Collection $roster): ?Instructor
    {
        $requested = trim((string) $request->query('instructor', ''));

        if ($requested === '') {
            return null;
        }

        return $roster->first(fn (Instructor $instructor) => (string) $instructor->instructor_id === $requested);
    }

    /**
     * Back to the screen the form was submitted from, filters and all, so a save
     * never throws away the department and search the admin had set up.
     */
    private function backToOrigin(array $flash)
    {
        return redirect()->back(302, [], route('assignments.index'))->with('flash', $flash);
    }

    private function allInstructors()
    {
        return Instructor::orderBy('lastname')->orderBy('firstname')->get();
    }

    private function allSubjects()
    {
        return SubjectCode::orderBy('subject_code')->get();
    }

    private function allSections()
    {
        return ProgramSection::orderBy('program')->orderBy('year_level')->orderBy('section')->get();
    }

    private function ensureSubjectScope(array $data, bool $enforceTerm): void
    {
        $matches = SubjectCode::whereKey($data['subject_id'])
            ->where('year_level', $data['year_level'])
            ->where(function ($query) use ($data) {
                $program = $data['program'];
                $query->where('program', $program)
                    ->orWhere('program', 'like', $program.',%')
                    ->orWhere('program', 'like', '%,'.$program)
                    ->orWhere('program', 'like', '%,'.$program.',%');
            })
            ->exists();

        if (! $matches) {
            throw ValidationException::withMessages([
                'subject_id' => 'Select a subject configured for the chosen program and year level.',
            ]);
        }

        $semester = SubjectCode::whereKey($data['subject_id'])->value('semester');

        if ($enforceTerm && ! AcademicTerm::includesSubject($semester)) {
            throw ValidationException::withMessages([
                'subject_id' => 'That subject belongs to '.($semester ?: 'another semester').
                    ', but the college is running '.AcademicTerm::semester().'. Change the active term in System Settings first.',
            ]);
        }
    }

    private function ensureManagedSections(array $data, $sections): void
    {
        $managedCount = ProgramSection::where('program', $data['program'])
            ->where('year_level', $data['year_level'])
            ->whereIn('section', $sections)
            ->count();

        if ($managedCount !== $sections->count()) {
            throw ValidationException::withMessages([
                'sections' => 'Select only sections configured in Section Management.',
            ]);
        }
    }
}
