<?php

namespace App\Http\Controllers;

use App\Models\AdminPersonnel;
use App\Support\ListPageSize;
use App\Support\PersonName;
use App\Support\RecordPurge;
use App\Support\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AdminPersonnelController extends Controller
{
    public function index(Request $request)
    {
        $query = AdminPersonnel::query();

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('firstname', 'like', "%{$request->search}%")
                    ->orWhere('lastname', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('personnel_id', 'like', "%{$request->search}%");
            });
        }
        if ($request->role) {
            $query->where('role', $request->role);
        }

        $order = in_array($request->order, ['ASC', 'DESC']) ? $request->order : 'DESC';
        $personnel = $query->orderBy('id', $order)->paginate(ListPageSize::from($request->limit))->withQueryString();
        $validRoles = AdminPersonnel::$validRoles;

        return view('mainAdmin.personnel.index', compact('personnel', 'validRoles'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'lastname' => PersonName::requiredRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:admin_personnel,email'],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', Rule::in(array_keys(AdminPersonnel::$validRoles))],
        ], [
            'email.unique' => 'This email address is already in use.',
            ...PersonName::messages('firstname', 'lastname'),
        ]);

        $plainPassword = ! empty($data['password']) ? $data['password'] : StrongPassword::generate();
        $data['password'] = Hash::make($plainPassword);
        $data['personnel_id'] = $this->generateId();

        AdminPersonnel::create($data);

        return redirect()->route('personnel.index')->with('flash', ['type' => 'success', 'message' => "Personnel account created. Initial password: {$plainPassword}"]);
    }

    public function update(Request $request, $id)
    {
        $p = AdminPersonnel::findOrFail($id);
        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'lastname' => PersonName::requiredRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', Rule::unique('admin_personnel', 'email')->ignore($id)],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'role' => ['required', Rule::in(array_keys(AdminPersonnel::$validRoles))],
        ], [
            'email.unique' => 'This email address is already in use.',
            ...PersonName::messages('firstname', 'lastname'),
        ]);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $p->update($data);

        return redirect()->route('personnel.index')->with('flash', ['type' => 'success', 'message' => 'Personnel updated.']);
    }

    public function destroy($id)
    {
        $personnel = AdminPersonnel::find($id);

        if (! $personnel) {
            return redirect()->route('personnel.index')->with('flash', ['type' => 'error', 'message' => 'That personnel record no longer exists.']);
        }

        DB::transaction(function () use ($personnel): void {
            RecordPurge::officePersonnel((string) $personnel->personnel_id, $personnel->email);
            $personnel->delete();
        });

        return redirect()->route('personnel.index')->with('flash', ['type' => 'success', 'message' => 'Record and all related details deleted.']);
    }

    private function generateId(): string
    {
        do {
            $id = 'AP-'.random_int(10000, 99999);
        } while (AdminPersonnel::where('personnel_id', $id)->exists());

        return $id;
    }
}
