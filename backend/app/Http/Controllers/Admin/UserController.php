<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Models\User;
use App\Services\Security\AccountSessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const PAPS_MANAGEABLE_ROLES = ['alumno', 'docente', 'coordinador', 'estratega', 'tutor'];

    public function __construct(
        private readonly AccountSessionManager $accountSessions
    ) {
        $this->middleware('role:admin|paps');
        $this->middleware(function (Request $request, $next) {
            $actor = $request->user();

            abort_if(
                $actor?->hasRole('paps')
                    && ! $actor->hasRole('admin')
                    && ! $actor->isApprovedPaps(),
                403
            );

            return $next($request);
        });
    }

    public function index(): View
    {
        $users = User::query()
            ->with('roles')
            ->when(
                $this->isRestrictedPaps(),
                fn ($query) => $query->whereDoesntHave(
                    'roles',
                    fn ($roles) => $roles->whereNotIn('name', self::PAPS_MANAGEABLE_ROLES)
                ),
                fn ($query) => $query->whereDoesntHave('roles', fn ($roles) => $roles->where('name', 'developer'))
            )
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
            ...$this->catalogOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(array_keys($this->availableRoles()))],
            'carrera' => ['nullable', 'string', 'max:100'],
            'turno' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'approved' => ['nullable', 'boolean'],
        ]);

        $payload = Arr::only($validated, ['name', 'email', 'password', 'carrera', 'turno']);
        $payload['is_active'] = $request->boolean('is_active');
        $payload['approved_at'] = $request->boolean('approved') ? Carbon::now() : null;

        DB::transaction(function () use ($payload, $validated): void {
            $user = new User;
            $user->forceFill($payload)->save();
            $user->syncRoles($validated['roles']);
        });

        return Redirect::route('admin.users.index')->with('status', __('Usuario creado correctamente.'));
    }

    public function edit(User $user): RedirectResponse|View
    {
        $this->ensureCanManage($user);

        if ($response = $this->preventSelfModification($user)) {
            return $response;
        }

        return view('admin.usuarios.edit', [
            'user' => $user->load('roles'),
            'roles' => $this->availableRoles(),
            ...$this->catalogOptions(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($user);

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
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', Password::defaults(), 'confirmed'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', Rule::in(array_keys($this->availableRoles()))],
            'carrera' => ['nullable', 'string', 'max:100'],
            'turno' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
            'approved' => ['nullable', 'boolean'],
        ]);

        if ($this->wouldRemoveLastAdmin($user, $validated['roles'])) {
            return Redirect::route('admin.users.edit', $user)->withInput()->withErrors([
                'roles' => __('Debe permanecer al menos un usuario con rol de administrador.'),
            ]);
        }

        $originalEmail = $user->email;
        $emailChanged = (string) $validated['email'] !== (string) $originalEmail;
        $passwordChanged = ! empty($validated['password']);
        $rolesChanged = collect($validated['roles'])->sort()->values()->all()
            !== $user->roles->pluck('name')->sort()->values()->all();

        $data = Arr::only($validated, ['name', 'email', 'carrera', 'turno']);
        $data['is_active'] = array_key_exists('is_active', $validated)
            ? $request->boolean('is_active')
            : $user->is_active;
        $data['approved_at'] = array_key_exists('approved', $validated)
            ? ($request->boolean('approved') ? ($user->approved_at ?? Carbon::now()) : null)
            : $user->approved_at;

        if ($emailChanged) {
            $data['email_verified_at'] = null;
        }

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $accessChanged = (bool) $data['is_active'] !== (bool) $user->is_active
            || ($data['approved_at'] === null) !== ($user->approved_at === null)
            || $rolesChanged;

        DB::transaction(function () use (
            $user,
            $data,
            $validated,
            $emailChanged,
            $passwordChanged,
            $accessChanged,
            $originalEmail
        ): void {
            $this->ensureAdminInvariant($user, $validated['roles']);

            $user->forceFill($data)->save();
            $user->syncRoles($validated['roles']);

            if ($emailChanged || $passwordChanged || $accessChanged) {
                $this->accountSessions->revokeAll($user, [$originalEmail]);
            }
        });

        return Redirect::route('admin.users.index')->with('status', __('Usuario actualizado correctamente.'));
    }

    public function approve(User $user): RedirectResponse
    {
        $this->ensureCanManage($user);

        if (! $user->approved_at) {
            $user->forceFill([
                'approved_at' => Carbon::now(),
                'is_active' => true,
            ])->save();
        }

        return Redirect::route('admin.users.index')->with('status', __('Usuario aprobado correctamente.'));
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($user);

        if ($response = $this->preventSelfModification($user)) {
            return $response;
        }

        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $isActive = (bool) $request->boolean('is_active');

        DB::transaction(function () use ($user, $isActive): void {
            $user->forceFill([
                'is_active' => $isActive,
            ])->save();

            if (! $isActive) {
                $this->accountSessions->revokeAll($user);
            }
        });

        return Redirect::route('admin.users.index')->with('status', __('Acceso actualizado correctamente.'));
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->ensureCanManage($user);

        if ($response = $this->preventSelfModification($user)) {
            return $response;
        }

        if ($this->isLastAdmin($user)) {
            return Redirect::route('admin.users.index')->withErrors([
                'user' => __('No es posible eliminar el único usuario con rol de administrador.'),
            ]);
        }

        DB::transaction(function () use ($user): void {
            $this->ensureAdminInvariant($user);
            $this->accountSessions->revokeAll($user);
            $user->delete();
        });

        return Redirect::route('admin.users.index')->with('status', __('Usuario eliminado correctamente.'));
    }

    private function availableRoles(): array
    {
        Role::query()->firstOrCreate([
            'name' => 'paps',
            'guard_name' => 'web',
        ]);

        return Role::query()
            ->when(
                $this->isRestrictedPaps(),
                fn ($query) => $query->whereIn('name', self::PAPS_MANAGEABLE_ROLES),
                fn ($query) => $query->where('name', '!=', 'developer')
            )
            ->orderBy('name')
            ->pluck('name', 'name')
            ->map(fn (string $role) => $role === 'alumno' ? 'Facilitador' : $role)
            ->all();
    }

    private function ensureCanManage(User $user): void
    {
        abort_if($user->hasRole('developer'), 403);

        if ($this->isRestrictedPaps()) {
            abort_if(
                $user->roles()->whereNotIn('name', self::PAPS_MANAGEABLE_ROLES)->exists(),
                403
            );
        }
    }

    private function isRestrictedPaps(): bool
    {
        $actor = auth()->user();

        return ($actor?->hasRole('paps') ?? false) && ! ($actor?->hasRole('admin') ?? false);
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

    /**
     * Re-check the final administrator invariant while holding row locks.
     *
     * @param  array<int, string>|null  $newRoles
     *
     * @throws ValidationException
     */
    private function ensureAdminInvariant(User $user, ?array $newRoles = null): void
    {
        if (! $user->hasRole('admin')) {
            return;
        }

        if (is_array($newRoles) && in_array('admin', $newRoles, true)) {
            return;
        }

        $adminIds = User::role('admin')
            ->lockForUpdate()
            ->pluck('users.id');

        if ($adminIds->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => __('Debe permanecer al menos un usuario con rol de administrador.'),
            ]);
        }
    }

    private function catalogOptions(): array
    {
        return [
            'carreras' => CatalogoCarrera::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->pluck('nombre')
                ->all(),
            'turnos' => CatalogoTurno::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->pluck('nombre')
                ->all(),
        ];
    }
}
