<?php

namespace App\Http\Controllers;

use App\Models\Registrar;
use App\Support\ListPageSize;
use App\Support\PersonName;
use App\Support\RecordPurge;
use App\Support\StrongPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegistrarController extends Controller
{
    public function index(Request $request)
    {
        $query = Registrar::query();
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('firstname', 'like', "%{$request->search}%")
                    ->orWhere('lastname', 'like', "%{$request->search}%")
                    ->orWhere('email', 'like', "%{$request->search}%")
                    ->orWhere('registrar_id', 'like', "%{$request->search}%");
            });
        }
        $registrars = $query->orderByDesc('id')->paginate(ListPageSize::from($request->limit))->withQueryString();

        return view('mainAdmin.registrar.index', compact('registrars'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'lastname' => PersonName::requiredRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', 'unique:registrar,email'],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'email.unique' => 'This email address is already in use.',
            ...PersonName::messages('firstname', 'lastname'),
        ]);
        $plainPassword = ! empty($data['password']) ? $data['password'] : StrongPassword::generate();
        $data['password'] = Hash::make($plainPassword);
        $data['registrar_id'] = $this->generateId();
        $data['role'] = 'registrar';
        Registrar::create($data);

        return redirect()->route('registrar.index')->with('flash', ['type' => 'success', 'message' => "Registrar account created. Initial password: {$plainPassword}"]);
    }

    public function update(Request $request, $id)
    {
        $r = Registrar::findOrFail($id);
        $data = $request->validate([
            'firstname' => PersonName::requiredRules(),
            'lastname' => PersonName::requiredRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:100', Rule::unique('registrar', 'email')->ignore($id)],
            'password' => ['nullable', 'string', 'max:128', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'email.unique' => 'This email address is already in use.',
            ...PersonName::messages('firstname', 'lastname'),
        ]);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $r->update($data);

        return redirect()->route('registrar.index')->with('flash', ['type' => 'success', 'message' => 'Registrar updated.']);
    }

    public function destroy($id)
    {
        $registrar = Registrar::find($id);

        if (! $registrar) {
            return redirect()->route('registrar.index')->with('flash', ['type' => 'error', 'message' => 'That registrar record no longer exists.']);
        }

        DB::transaction(function () use ($registrar): void {
            RecordPurge::registrar((string) $registrar->registrar_id, $registrar->email);
            $registrar->delete();
        });

        return redirect()->route('registrar.index')->with('flash', ['type' => 'success', 'message' => 'Registrar and all related details deleted.']);
    }

    private function generateId(): string
    {
        do {
            $id = 'REG-'.random_int(10000, 99999);
        } while (Registrar::where('registrar_id', $id)->exists());

        return $id;
    }
}
