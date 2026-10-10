<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * "Forgot password" for email accounts, used by the web app. The emailed link opens the web
 * app's reset page (see AppServiceProvider). Also how a Google-only account can add a password.
 */
class PasswordResetController extends Controller
{
    /**
     * Always answers the same way, so it never tells anyone which emails have an account.
     * Guests (no email) and deleted accounts simply never receive anything.
     */
    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user && ! $user->isGuest()) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return response()->json(['sent' => true]);
    }

    /**
     * Sets the new password and signs the account out everywhere: whoever asked for the reset
     * might be locking someone else out.
     */
    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $status = Password::reset(
            ['email' => $data['email'], 'token' => $data['token'], 'password' => $data['password']],
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json(['message' => 'This reset link is invalid or has expired. Ask for a new one.'], 422);
        }

        return response()->json(['reset' => true]);
    }
}
