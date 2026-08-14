<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Security\AccountSessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(
        Request $request,
        AccountSessionManager $accountSessions
    ): RedirectResponse {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        DB::transaction(function () use ($request, $validated, $accountSessions): void {
            $user = $request->user();

            $user->forceFill([
                'password' => Hash::make($validated['password']),
            ])->save();

            $accountSessions->revokeOtherSessions(
                $user,
                $request->session()->getId()
            );
        });

        $request->session()->regenerate();

        return back()->with('status', 'password-updated');
    }
}
