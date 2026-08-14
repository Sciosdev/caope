<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\CatalogoCarrera;
use App\Models\CatalogoTurno;
use App\Services\Security\AccountSessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'carreras' => CatalogoCarrera::activos()->pluck('nombre'),
            'turnos' => CatalogoTurno::activos()->pluck('nombre'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(
        ProfileUpdateRequest $request,
        AccountSessionManager $accountSessions
    ): RedirectResponse {
        $user = $request->user();
        $originalEmail = $user->email;
        $emailChanged = (string) $request->validated('email') !== (string) $originalEmail;

        DB::transaction(function () use ($request, $accountSessions, $user, $originalEmail, $emailChanged): void {
            $user->fill(Arr::except($request->validated(), ['current_password']));

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            if ($emailChanged) {
                $accountSessions->revokeOtherSessions(
                    $user,
                    $request->session()->getId(),
                    [$originalEmail]
                );
            }
        });

        if ($emailChanged) {
            $request->session()->regenerate();
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(
        Request $request,
        AccountSessionManager $accountSessions
    ): RedirectResponse {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        DB::transaction(function () use ($accountSessions, $user): void {
            $accountSessions->revokeAll($user);
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
