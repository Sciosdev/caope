<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin');
    }

    public function index(): View
    {
        $users = User::query()
            ->with('roles')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.usuarios.index', [
            'users' => $users,
        ]);
    }

    public function create(): View
    {
        return view('admin.usuarios.create', [
            'roles' => $this->availableRoles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'carrera' => ['nullable', 'string', 'max:100'],
            'turno' => ['nullable', 'string', 'max:100'],
        ]);

        $payload = Arr::only($validated, ['name', 'email', 'password', 'carrera', 'turno']);

        $user = User::create($payload);
        $user->syncRoles($validated['roles']);

        return Redirect::route('admin.users.index')->with('status', __('Usuario creado correctamente.'));
    }

    public function edit(User $user): RedirectResponse|View
    {
        if ($response = $this->preventSelfModification($user)) {
            return $response;
        }

        return view('admin.usuarios.edit', [
            'user' => $user->load('roles'),
            'roles' => $this->availableRoles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($response = $this->preventSelfModification($user)) {
            return $response;
        }

        if (! $request->filled('password')) {
            $request->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
            'carrera' => ['nullable', 'string', 'max:100'],
            'turno' => ['nullable', 'string', 'max:100'],
        ]);

        if ($this->wouldRemoveLastAdmin($user, $validated['roles'])) {
            return Redirect::route('admin.users.edit', $user)->withInput()->withErrors([
                'roles' => __('Debe permanecer al menos un usuario con rol de administrador.'),
            ]);
        }

        $data = Arr::only($validated, ['name', 'email', 'carrera', 'turno']);

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);
        $user->syncRoles($validated['roles']);

        return Redirect::route('admin.users.index')->with('status', __('Usuario actualizado correctamente.'));
    }


    public function destroy(User $user): RedirectResponse
    {
        if ($response = $this->preventSelfModification($user)) {
            return $response;
        }

        if ($this->isLastAdmin($user)) {
            return Redirect::route('admin.users.index')->withErrors([
                'user' => __('No es posible eliminar el único usuario con rol de administrador.'),
            ]);
        }

        $user->delete();

        return Redirect::route('admin.users.index')->with('status', __('Usuario eliminado correctamente.'));
    }

    private function availableRoles(): array
    {
        return Role::query()->orderBy('name')->pluck('name', 'name')->all();
    }

    private function preventSelfModification(User $user): ?RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return Redirect::route('admin.users.index')->withErrors([
                'user' => __('No puedes modificar tu propio usuario desde esta sección.'),
            ]);
        }

        return null;
    }

    private function wouldRemoveLastAdmin(User $user, array $roleNames): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        if (in_array('admin', $roleNames, true)) {
            return false;
        }

        return User::role('admin')->count() <= 1;
    }

    private function isLastAdmin(User $user): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        return User::role('admin')->count() <= 1;
    }
}
