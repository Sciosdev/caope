<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DeveloperConsoleSettings;
use App\Services\GitHubDeploymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

class DeveloperConsoleActivationController extends Controller
{
    public function show(Request $request, DeveloperConsoleSettings $settings): Response|RedirectResponse
    {
        if (! $settings->canActivate()) {
            if ($settings->enabled() && $request->user()?->hasRole('developer')) {
                return redirect()->route('developer.index');
            }

            abort(404);
        }

        return response()->view('developer.activate', [
            'users' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
            'recovering' => $settings->encryptedFileIsInvalid(),
        ])->withHeaders(['Cache-Control' => 'no-store, private']);
    }

    public function store(
        Request $request,
        DeveloperConsoleSettings $settings,
        GitHubDeploymentService $github,
    ): RedirectResponse {
        $recovering = $settings->encryptedFileIsInvalid();
        $validated = $request->validate([
            'github_token' => ['required', 'string', 'min:20', 'max:1024'],
            'developer_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'confirm_recovery' => $recovering ? ['required', 'accepted'] : ['nullable'],
        ], [
            'confirm_recovery.accepted' => 'Confirma que deseas reemplazar la configuración que ya no puede descifrarse.',
        ]);

        $lock = Cache::lock('developer-console:activation', 60);

        if (! $lock->get()) {
            return back()->withErrors([
                'activation' => 'Ya se está procesando otra activación.',
            ]);
        }

        try {
            if (! $settings->canActivate()) {
                return back()->withErrors([
                    'activation' => 'La consola ya fue activada. Actualiza la página.',
                ]);
            }

            try {
                $workflowId = $github->validateActivationToken((string) $validated['github_token']);
            } catch (Throwable) {
                return back()->withErrors([
                    'github_token' => 'El token no es válido o no tiene Actions: lectura y escritura para Sciosdev/caope.',
                ]);
            }

            $developer = User::query()->findOrFail((int) $validated['developer_user_id']);
            $role = Role::query()->firstOrCreate([
                'name' => 'developer',
                'guard_name' => 'web',
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $developer->assignRole($role);

            try {
                $settings->storeProduction(
                    (string) $validated['github_token'],
                    $workflowId,
                    (int) $request->user()->getAuthIdentifier(),
                );
            } catch (Throwable $exception) {
                report($exception);

                return back()->withErrors([
                    'activation' => 'No fue posible guardar la configuración cifrada en el almacenamiento privado.',
                ]);
            }

            if ($developer->is($request->user())) {
                return redirect()->route('developer.index')
                    ->with('status', 'Consola activada. Producción ya puede solicitar despliegues sin acceso de FESI.');
            }

            return redirect()->route('dashboard')->with(
                'status',
                "Consola activada para {$developer->email}. Esa cuenta ya puede entrar a /desarrollo."
            );
        } finally {
            $lock->release();
        }
    }

    public function rotate(
        Request $request,
        DeveloperConsoleSettings $settings,
        GitHubDeploymentService $github,
    ): RedirectResponse {
        abort_unless($settings->hasEncryptedSettings(), 404);

        $validated = $request->validate([
            'github_token' => ['required', 'string', 'min:20', 'max:1024'],
        ]);

        $lock = Cache::lock('developer-console:credentials', 60);

        if (! $lock->get()) {
            return back()->withErrors([
                'credentials' => 'Ya se está procesando otra actualización de credenciales.',
            ]);
        }

        try {
            try {
                $workflowId = $github->validateActivationToken((string) $validated['github_token']);
            } catch (Throwable) {
                return back()->withErrors([
                    'github_token' => 'El token no es válido o no tiene Actions: lectura y escritura para Sciosdev/caope.',
                ]);
            }

            try {
                $settings->rotateGithubToken(
                    (string) $validated['github_token'],
                    $workflowId,
                    (int) $request->user()->getAuthIdentifier(),
                );
            } catch (Throwable $exception) {
                report($exception);

                return back()->withErrors([
                    'credentials' => 'No fue posible guardar el nuevo token cifrado.',
                ]);
            }

            return back()->with('status', 'Credenciales de GitHub actualizadas correctamente.');
        } finally {
            $lock->release();
        }
    }
}
