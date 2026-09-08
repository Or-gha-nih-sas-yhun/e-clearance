<?php

namespace App\Http\Controllers;

use App\Models\StudentAccount;
use App\Models\StudentRegistry;
use App\Support\ListPageSize;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Main Admin management of the self-registration roster.
 *
 * Flipping a row back to 'inactive' is what lets a student register again, so
 * it is refused while a student_account still exists for that entry — the
 * account has to be deleted first, which flips the row back on its own.
 */
class StudentRegistryController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('student_registry')) {
            return view('mainAdmin.student-registry.index', [
                'entries' => null,
                'counts' => ['active' => 0, 'inactive' => 0],
            ]);
        }

        $query = StudentRegistry::query();

        if ($search = $request->string('search')->trim()->value()) {
            $query->where(function ($builder) use ($search) {
                $builder->where('student_id', 'like', "%{$search}%")
                    ->orWhere('ms_account', 'like', "%{$search}%");
            });
        }

        if (in_array($request->status, [StudentRegistry::STATUS_ACTIVE, StudentRegistry::STATUS_INACTIVE], true)) {
            $query->where('status', $request->status);
        }

        $order = in_array($request->order, ['ASC', 'DESC'], true) ? $request->order : 'DESC';
        $limit = ListPageSize::from($request->limit);

        return view('mainAdmin.student-registry.index', [
            'entries' => $query->orderBy('id', $order)->paginate($limit)->withQueryString(),
            'counts' => [
                'active' => StudentRegistry::where('status', StudentRegistry::STATUS_ACTIVE)->count(),
                'inactive' => StudentRegistry::where('status', StudentRegistry::STATUS_INACTIVE)->count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->assertTableExists();

        $data = $request->validate([
            'student_id' => ['required', 'regex:/^\d{4}-\d{4}$/', 'unique:student_registry,student_id'],
            'ms_account' => ['required', 'string', 'lowercase', 'email', 'max:150', 'unique:student_registry,ms_account'],
            'status' => ['required', Rule::in([StudentRegistry::STATUS_ACTIVE, StudentRegistry::STATUS_INACTIVE])],
        ], [
            'student_id.regex' => 'Student ID must look like 2026-0001.',
            'student_id.unique' => 'That student ID is already on the registration list.',
            'ms_account.unique' => 'That Microsoft account is already on the registration list.',
            'ms_account.lowercase' => 'Enter the Microsoft account in lowercase.',
        ]);

        StudentRegistry::create($data + [
            'registered_at' => $data['status'] === StudentRegistry::STATUS_ACTIVE ? now() : null,
        ]);

        return redirect()->route('student-registry.index')
            ->with('flash', ['type' => 'success', 'message' => 'Registration entry added.']);
    }

    public function update(Request $request, int $id)
    {
        $this->assertTableExists();
        $entry = StudentRegistry::findOrFail($id);

        $data = $request->validate([
            'student_id' => ['required', 'regex:/^\d{4}-\d{4}$/', Rule::unique('student_registry', 'student_id')->ignore($entry->id)],
            'ms_account' => ['required', 'string', 'lowercase', 'email', 'max:150', Rule::unique('student_registry', 'ms_account')->ignore($entry->id)],
            'status' => ['required', Rule::in([StudentRegistry::STATUS_ACTIVE, StudentRegistry::STATUS_INACTIVE])],
        ], [
            'student_id.regex' => 'Student ID must look like 2026-0001.',
            'student_id.unique' => 'That student ID is already on the registration list.',
            'ms_account.unique' => 'That Microsoft account is already on the registration list.',
            'ms_account.lowercase' => 'Enter the Microsoft account in lowercase.',
        ]);

        // Re-opening an entry whose account still exists would let one student
        // hold two accounts, so send the admin to the delete action instead.
        if ($data['status'] === StudentRegistry::STATUS_INACTIVE && $this->accountExists($entry)) {
            return back()->with('flash', [
                'type' => 'error',
                'title' => 'Cannot set to inactive',
                'message' => "A student account still exists for {$entry->student_id}. Delete that student account first — doing so re-opens this entry automatically.",
            ]);
        }

        $entry->update($data + [
            'registered_at' => $data['status'] === StudentRegistry::STATUS_ACTIVE ? ($entry->registered_at ?? now()) : null,
        ]);

        return redirect()->route('student-registry.index')
            ->with('flash', ['type' => 'success', 'message' => 'Registration entry updated.']);
    }

    public function destroy(int $id)
    {
        $this->assertTableExists();
        $entry = StudentRegistry::findOrFail($id);

        if ($this->accountExists($entry)) {
            return back()->with('flash', [
                'type' => 'error',
                'title' => 'Cannot remove entry',
                'message' => "A student account still exists for {$entry->student_id}. Delete the student account first.",
            ]);
        }

        $entry->delete();

        return redirect()->route('student-registry.index')
            ->with('flash', ['type' => 'success', 'message' => 'Registration entry removed.']);
    }

    private function accountExists(StudentRegistry $entry): bool
    {
        return StudentAccount::where('student_id', $entry->student_id)->exists()
            || StudentAccount::whereRaw('LOWER(email) = ?', [strtolower((string) $entry->ms_account)])->exists();
    }

    private function assertTableExists(): void
    {
        abort_unless(Schema::hasTable('student_registry'), 503, 'The student_registry table has not been created yet.');
    }
}
