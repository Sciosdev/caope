<?php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccountSessionManager
{
    /**
     * Revoke every active browser session and recovery token for the user.
     *
     * @param  array<int, string>  $additionalEmails
     */
    public function revokeAll(User $user, array $additionalEmails = []): void
    {
        $this->deleteSessions($user);
        $this->deletePasswordResetTokens($user, $additionalEmails);
        $this->rotateRememberToken($user);
    }

    /**
     * Keep the current browser session while revoking every other session.
     *
     * @param  array<int, string>  $additionalEmails
     */
    public function revokeOtherSessions(
        User $user,
        ?string $currentSessionId,
        array $additionalEmails = []
    ): void {
        $this->deleteSessions($user, $currentSessionId);
        $this->deletePasswordResetTokens($user, $additionalEmails);
        $this->rotateRememberToken($user);
    }

    private function deleteSessions(User $user, ?string $exceptSessionId = null): void
    {
        $table = (string) config('session.table', 'sessions');

        if ($table === '' || ! Schema::hasTable($table)) {
            return;
        }

        $query = DB::table($table)->where('user_id', $user->getKey());

        if (is_string($exceptSessionId) && $exceptSessionId !== '') {
            $query->where('id', '!=', $exceptSessionId);
        }

        $query->delete();
    }

    /**
     * @param  array<int, string>  $additionalEmails
     */
    private function deletePasswordResetTokens(User $user, array $additionalEmails): void
    {
        if (! Schema::hasTable('password_reset_tokens')) {
            return;
        }

        $emails = collect([$user->email, ...$additionalEmails])
            ->filter(fn ($email): bool => is_string($email) && $email !== '')
            ->map(fn (string $email): string => Str::lower($email))
            ->unique()
            ->values()
            ->all();

        if ($emails !== []) {
            DB::table('password_reset_tokens')->whereIn('email', $emails)->delete();
        }
    }

    private function rotateRememberToken(User $user): void
    {
        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->saveQuietly();
    }
}
