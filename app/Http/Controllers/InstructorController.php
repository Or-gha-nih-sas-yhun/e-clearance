<?php

namespace App\Http\Controllers;

use App\Models\Instructor;
use App\Support\InstructorDepartment;
use App\Support\ListPageSize;
use App\Support\PersonName;
use App\Support\RecordPurge;
use App\Support\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class InstructorController extends Controller
{
    public function index(Request $request)
    {
        $query = Instructor::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('instructor_id', 'like', "%{$request->search}%")
                    ->orWhere('firstname', 'like', "%{$request->search}%")
                    ->orWhere('lastname', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('department', 'like', "%{$request->search}%");
            });
        }
        if ($request->department) {
            // A filter on College of Education still has to find the BSED and
            // BEED rows written before the two were merged into one faculty.
            $query->whereIn('department', InstructorDepartment::storedValues($request->department));
        }

        $employmentAvailable = Instructor::tracksEmploymentStatus();
        if ($employmentAvailable && in_array($request->employment, Instructor::EMPLOYMENT_STATUSES, true)) {
            $query->where('employment_status', $request->employment);
        }

        $order = in_array($request->order, ['ASC', 'DESC']) ? $request->order : 'DESC';
        $limit = ListPageSize::from($request->limit);

        $instructors = $query->orderBy('id', $order)->paginate($limit)->withQueryString();

        // Display only: a legacy 'BSED'/'BEED' row reads as its college, and
        // saving the edit form is what actually rewrites it.
        $instructors->getCollection()->transform(function (Instructor $instructor): Instructor {
            $instructor->department = $instructor->department_label;

            return $instructor;
        });

        return view('mainAdmin.instructors.index', [
            'instructors' => $instructors,
            'departments' => InstructorDepartment::OPTIONS,
            'employmentStatuses' => Instructor::EMPLOYMENT_STATUSES,
            'employmentAvailable' => $employmentAvailable,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'instructor_id' => ['required', 'regex:/^\d{4}$/', 'unique:instructor_account,instructor_id'],
            'firstname' => PersonName::requiredRules(),
            'middlename' => PersonName::optionalRules(),
            'lastname' => PersonName::requiredRules(),
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN.\s\'\-]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:instructor_account,email'],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'department' => ['required', Rule::in(InstructorDepartment::accepted())],
            'employment_status' => $this->employmentStatusRules(),
        ], [
            'instructor_id.unique' => 'This employee ID is already in use.',
            'email.unique' => 'This email address is already in use.',
            'employment_status.required' => 'Choose whether this instructor is regular or part timer.',
            ...PersonName::messages('firstname', 'middlename', 'lastname'),
        ]);
        $plainPassword = ! empty($data['password']) ? $data['password'] : StrongPassword::generate();
        $data['password'] = Hash::make($plainPassword);
        $this->normalizeFaculty($data);
        Instructor::create($data);

        return redirect()->route('instructors.index')->with('flash', ['type' => 'success', 'message' => "New instructor added. Initial password: {$plainPassword}"]);
    }

    public function update(Request $request, $instructor_id)
    {
        $inst = Instructor::where('instructor_id', $instructor_id)->firstOrFail();
        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'middlename' => PersonName::optionalRules(),
            'lastname' => PersonName::requiredRules(),
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN.\s\'\-]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', Rule::unique('instructor_account', 'email')->ignore($inst->id)],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'department' => ['required', Rule::in(InstructorDepartment::accepted())],
            'employment_status' => $this->employmentStatusRules(),
        ], [
            'email.unique' => 'This email address is already in use.',
            'employment_status.required' => 'Choose whether this instructor is regular or part timer.',
            ...PersonName::messages('firstname', 'middlename', 'lastname'),
        ]);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $this->normalizeFaculty($data);
        $inst->update($data);

        return redirect()->route('instructors.index')->with('flash', ['type' => 'success', 'message' => 'Instructor updated.']);
    }

    public function destroy($instructor_id)
    {
        $instructor = Instructor::where('instructor_id', $instructor_id)->first();

        if (! $instructor) {
            return redirect()->route('instructors.index')->with('flash', ['type' => 'error', 'message' => 'That instructor record no longer exists.']);
        }

        DB::transaction(function () use ($instructor): void {
            RecordPurge::instructor((string) $instructor->instructor_id, $instructor->email);
            $instructor->delete();
        });

        return redirect()->route('instructors.index')->with('flash', ['type' => 'success', 'message' => 'Instructor and all related details deleted.']);
    }

    public function reset($instructor_id)
    {
        $instructor = Instructor::where('instructor_id', $instructor_id)->firstOrFail();
        $temporaryPassword = StrongPassword::generate(16);

        $instructor->update(['password' => Hash::make($temporaryPassword)]);

        return redirect()->route('instructors.index')->with('flash', [
            'type' => 'success',
            'message' => "Password reset. One-time temporary password: {$temporaryPassword}",
        ]);
    }

    /** Position is required only once there is a column to hold it. */
    private function employmentStatusRules(): array
    {
        return Instructor::tracksEmploymentStatus()
            ? ['required', Rule::in(Instructor::EMPLOYMENT_STATUSES)]
            : ['nullable'];
    }

    /**
     * Store the canonical department, and drop the position on a database that
     * has no column for it rather than failing the whole save.
     *
     * @param  array<string, mixed>  $data
     */
    private function normalizeFaculty(array &$data): void
    {
        $data['department'] = InstructorDepartment::canonical($data['department']);

        if (Instructor::tracksEmploymentStatus()) {
            $data['employment_status'] = ($data['employment_status'] ?? null) ?: Instructor::EMPLOYMENT_REGULAR;
        } else {
            unset($data['employment_status']);
        }
    }
}
