<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const PASSWORD_RESET_LINK_RESPONSE = 'If an active account exists for that email, we will send a password reset link that expires in 1 hour.';
    private const PASSWORD_RESET_FAILED_RESPONSE = 'We could not reset your password. Please check the reset link and try again.';

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended(route('dashboard'));
        }

        return back()->withErrors(['email' => 'Invalid credentials or inactive account.'])->onlyInput('email');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        if (User::where('email', $validated['email'])->where('is_active', true)->exists()) {
            Password::sendResetLink($validated);
        }

        return back()->with('success', self::PASSWORD_RESET_LINK_RESPONSE);
    }

    public function showResetPassword(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'email' => $request->query('email'),
            'token' => $token,
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'confirmed', 'min:10', 'max:64'],
        ], [
            'password.min' => 'Password must be between 10 and 64 characters. Letters, numbers, symbols, and spaces are allowed.',
            'password.max' => 'Password must be between 10 and 64 characters. Letters, numbers, symbols, and spaces are allowed.',
        ]);

        if (! User::where('email', $request->email)->where('is_active', true)->exists()) {
            return back()->withErrors(['email' => self::PASSWORD_RESET_FAILED_RESPONSE])->onlyInput('email');
        }

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success', 'Password reset successfully. You can now sign in.')
            : back()->withErrors(['email' => self::PASSWORD_RESET_FAILED_RESPONSE])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
