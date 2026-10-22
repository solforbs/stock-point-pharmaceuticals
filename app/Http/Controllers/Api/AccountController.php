<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Every signed-in person's own account: the email they sign in with and
 * their password. Both changes ask for the current password, so an
 * unattended open session cannot take the account over.
 */
class AccountController extends ApiController
{
    /** PATCH /api/account/email */
    public function updateEmail(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => ['required', 'string'],
        ]);
        $this->assertCurrentPassword($user->password, $data['current_password']);

        $before = $user->email;
        $user->forceFill(['email' => strtolower(trim($data['email']))])->save();
        AuditLog::record('ACCOUNT_EMAIL_CHANGED', 'user', (string) $user->id, [
            'reference' => $user->email,
            'before_json' => ['email' => $before],
            'after_json' => ['email' => $user->email],
        ]);

        return response()->json(['email' => $user->email]);
    }

    /** PATCH /api/account/password — the new password replaces the old at once. */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'confirmed', 'different:current_password'],
        ], [
            'password.min' => 'Use at least 12 characters.',
            'password.confirmed' => 'The two new passwords do not match.',
            'password.different' => 'Choose a password different from the current one.',
        ]);
        $this->assertCurrentPassword($user->password, $data['current_password']);

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        AuditLog::record('ACCOUNT_PASSWORD_CHANGED', 'user', (string) $user->id, ['reference' => $user->email]);

        return response()->json(['changed' => true]);
    }

    private function assertCurrentPassword(string $hash, string $given): void
    {
        if (! Hash::check($given, $hash)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is not right.']);
        }
    }
}
