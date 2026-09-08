<?php

namespace App\Http\Controllers;

use App\Models\ProgramSection;
use App\Models\Student;
use App\Support\ListPageSize;
use App\Support\PersonName;
use App\Support\RecordPurge;
use App\Support\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $query = Student::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('student_id', 'like', "%{$request->search}%")
                    ->orWhere('firstname', 'like', "%{$request->search}%")
                    ->orWhere('lastname', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%");
            });
        }
        if ($request->filter) {
            $query->where('student_type', $request->filter);
        }
        if ($request->program) {
            $query->where('program', $request->program);
        }
        if ($request->year_level) {
            $query->where('year_level', $request->year_level);
        }

        $order = in_array($request->order, ['ASC', 'DESC']) ? $request->order : 'DESC';
        $limit = ListPageSize::from($request->limit);

        $students = $query->orderBy('id', $order)->paginate($limit)->withQueryString();

        // Sections for dropdowns
        $sectionsData = ProgramSection::orderBy('program')->orderBy('year_level')->orderBy('section')->get();
        $programsList = ['BSIT', 'BSHM', 'BSED', 'BEED', 'BSBA'];
        $yearLevelOptions = [
            '1' => '1st Year',
            '2' => '2nd Year',
            '3' => '3rd Year',
            '4' => '4th Year',
        ];

        return view('mainAdmin.students.index', compact('students', 'sectionsData', 'programsList', 'yearLevelOptions'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'regex:/^\d{4}-\d{4}$/', 'unique:student_account,student_id'],
            'firstname' => PersonName::requiredRules(),
            'middlename' => PersonName::optionalRules(),
            'lastname' => PersonName::requiredRules(),
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN.\s\'\-]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:student_account,email'],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'program' => ['required', Rule::in(['BSIT', 'BSHM', 'BSED', 'BEED', 'BSBA'])],
            'year_level' => ['required', Rule::in(['1', '2', '3', '4'])],
            'section' => ['required', 'string', 'max:50'],
            'student_type' => ['required', Rule::in(['Regular', 'Irregular'])],
        ], [
            'student_id.unique' => 'This student ID is already in use.',
            'email.unique' => 'This email address is already in use.',
            ...PersonName::messages('firstname', 'middlename', 'lastname'),
        ]);

        $plainPassword = ! empty($data['password']) ? $data['password'] : StrongPassword::generate();
        $data['password'] = Hash::make($plainPassword);
        $this->ensureManagedSection($data);
        Student::create($data);

        return redirect()->route('students.index')->with('flash', ['type' => 'success', 'message' => "New student added. Initial password: {$plainPassword}"]);
    }

    public function update(Request $request, $student_id)
    {
        $student = Student::where('student_id', $student_id)->firstOrFail();

        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'middlename' => PersonName::optionalRules(),
            'lastname' => PersonName::requiredRules(),
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN.\s\'\-]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', Rule::unique('student_account', 'email')->ignore($student->id)],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'program' => ['required', Rule::in(['BSIT', 'BSHM', 'BSED', 'BEED', 'BSBA'])],
            'year_level' => ['required', Rule::in(['1', '2', '3', '4'])],
            'section' => ['required', 'string', 'max:50'],
            'student_type' => ['required', Rule::in(['Regular', 'Irregular'])],
        ], [
            'email.unique' => 'This email address is already in use.',
            ...PersonName::messages('firstname', 'middlename', 'lastname'),
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $this->ensureManagedSection($data);
        $student->update($data);

        return redirect()->route('students.index')->with('flash', ['type' => 'success', 'message' => 'Student record updated.']);
    }

    public function destroy($student_id)
    {
        $student = Student::where('student_id', $student_id)->first();

        if (! $student) {
            return redirect()->route('students.index')->with('flash', ['type' => 'error', 'message' => 'That student record no longer exists.']);
        }

        DB::transaction(function () use ($student): void {
            RecordPurge::student((string) $student->student_id, $student->email);
            $student->delete();
        });

        return redirect()->route('students.index')->with('flash', ['type' => 'success', 'message' => 'Student record and all related details deleted.']);
    }

    public function reset($student_id)
    {
        $student = Student::where('student_id', $student_id)->firstOrFail();
        $temporaryPassword = StrongPassword::generate(16);

        $student->update(['password' => Hash::make($temporaryPassword)]);

        return redirect()->route('students.index')->with('flash', [
            'type' => 'success',
            'message' => "Password reset. One-time temporary password: {$temporaryPassword}",
        ]);
    }

    private function ensureManagedSection(array $data): void
    {
        $exists = ProgramSection::where('program', $data['program'])
            ->where('year_level', $data['year_level'])
            ->where('section', $data['section'])
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'section' => 'Select a section configured in Section Management for the selected program and year level.',
            ]);
        }
    }
}
