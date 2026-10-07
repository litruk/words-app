<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ApplyClientPreferences;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Password;

/**
 * The page the "forgot password" mail links to. The app itself only asks for
 * the mail; the new password is set here, in the browser.
 */
class ResetPasswordController extends Controller
{
    public function show(Request $request, string $token): View
    {
        $this->speakTheUsersLanguage($request);

        return view('reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function update(Request $request): View|RedirectResponse
    {
        $this->speakTheUsersLanguage($request);

        $credentials = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $status = Password::reset($credentials, function (User $user, string $password) {
            $user->password = $password;
            $user->save();

            // Every device has to sign in again with the new password.
            $user->tokens()->delete();
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['password' => __($status)]);
        }

        return view('reset-password-done');
    }

    private function speakTheUsersLanguage(Request $request): void
    {
        $user = User::where('email', (string) $request->input('email', $request->query('email')))->first();

        App::setLocale($user?->native_language ?? $request->getPreferredLanguage(ApplyClientPreferences::LOCALES) ?? 'en');
    }
}
