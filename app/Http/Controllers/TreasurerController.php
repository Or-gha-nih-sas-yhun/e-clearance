<?php

namespace App\Http\Controllers;

use App\Models\ProgramSection;
use App\Models\Treasurer;
use App\Support\ListPageSize;
use App\Support\PersonName;
use App\Support\RecordPurge;
use App\Support\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TreasurerController extends Controller
{
    private array $departments = ['BSIT', 'BSED', 'BEED', 'BSBA', 'BSHM'];

    private array $programs = ['BSIT', 'BSBA', 'BSHM', 'BSED', 'BEED'];

    private array $types = ['department' => 'Department Treasurer', 'section' => 'Section Treasurer'];

    public function index(Request $request)
    {
        $query = Treasurer::query();

        if ($request->type) {
            $query->where('treasurer_type', $request->type);
        }
        if ($request->department) {
            $query->where('department', $request->department);
        }
        if ($request->program) {
            $query->where('program', $request->program);
        }
        if ($request->year_level) {
            $query->where('year_level', $request->year_level);
        }
        if ($request->section) {
            $query->where('section', $request->section);
        }

        $treasurers = $query->orderByDesc('id')->paginate(ListPageSize::from($request->limit))->withQueryString();
        $sections = ProgramSection::orderBy('program')->orderBy('year_level')->orderBy('section')->get();

        return view('mainAdmin.treasurers.index', compact('treasurers', 'sections'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'middlename' => PersonName::optionalRules(),
            'lastname' => PersonName::requiredRules(),
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN.\s\'\-]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:treasurers,email'],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'treasurer_type' => ['required', Rule::in(array_keys($this->types))],
            'department' => 'nullable|required_if:treasurer_type,department|in:BSIT,BSED,BEED,BSBA,BSHM',
            'program' => 'nullable|required_if:treasurer_type,section|in:BSIT,BSBA,BSHM,BSED,BEED',
            'year_level' => 'nullable|required_if:treasurer_type,section|integer|in:1,2,3,4',
            'section' => [
                'nullable',
                'required_if:treasurer_type,section',
                'string',
                'max:50',
                ...($request->input('treasurer_type') === 'section' ? [
                    Rule::exists('program_sections', 'section')->where(fn ($query) => $query
                        ->where('program', $request->input('program'))
                        ->where('year_level', $request->input('year_level'))),
                ] : []),
            ],
        ], [
            'email.unique' => 'This email address is already in use.',
            'section.exists' => 'Select a section configured for the chosen program and year level.',
            ...PersonName::messages('firstname', 'middlename', 'lastname'),
        ]);

        $plainPassword = ! empty($data['password']) ? $data['password'] : StrongPassword::generate();
        $data['treasurer_id'] = $this->generateId();
        $data['password'] = Hash::make($plainPassword);
        Treasurer::create($data);

        return redirect()->route('treasurers.index')->with('flash', ['type' => 'success', 'message' => "Treasurer account created. Initial password: {$plainPassword}"]);
    }

    public function update(Request $request, $id)
    {
        $treasurer = Treasurer::findOrFail($id);

        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'middlename' => PersonName::optionalRules(),
            'lastname' => PersonName::requiredRules(),
            'suffix' => ['nullable', 'string', 'max:10', 'regex:/^[\pL\pN.\s\'\-]+$/u'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', Rule::unique('treasurers', 'email')->ignore($id)],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'treasurer_type' => ['required', Rule::in(array_keys($this->types))],
            'department' => 'nullable|required_if:treasurer_type,department|in:BSIT,BSED,BEED,BSBA,BSHM',
            'program' => 'nullable|required_if:treasurer_type,section|in:BSIT,BSBA,BSHM,BSED,BEED',
            'year_level' => 'nullable|required_if:treasurer_type,section|integer|in:1,2,3,4',
            'section' => [
                'nullable',
                'required_if:treasurer_type,section',
                'string',
                'max:50',
                ...($request->input('treasurer_type') === 'section' ? [
                    Rule::exists('program_sections', 'section')->where(fn ($query) => $query
                        ->where('program', $request->input('program'))
                        ->where('year_level', $request->input('year_level'))),
                ] : []),
            ],
        ], [
            'email.unique' => 'This email address is already in use.',
            'section.exists' => 'Select a section configured for the chosen program and year level.',
            ...PersonName::messages('firstname', 'middlename', 'lastname'),
        ]);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $treasurer->update($data);

        return redirect()->route('treasurers.index')->with('flash', ['type' => 'success', 'message' => 'Treasurer updated.']);
    }

    public function destroy($id)
    {
        $treasurer = Treasurer::find($id);

        if (! $treasurer) {
            return redirect()->route('treasurers.index')->with('flash', ['type' => 'error', 'message' => 'That treasurer record no longer exists.']);
        }

        DB::transaction(function () use ($treasurer): void {
            RecordPurge::treasurer((string) $treasurer->treasurer_id, $treasurer->email);
            $treasurer->delete();
        });

        return redirect()->route('treasurers.index')->with('flash', ['type' => 'success', 'message' => 'Treasurer and all related details deleted.']);
    }

    private function generateId(): string
    {
        do {
            $id = 'TR-'.random_int(10000, 99999);
        } while (Treasurer::where('treasurer_id', $id)->exists());

        return $id;
    }
}
