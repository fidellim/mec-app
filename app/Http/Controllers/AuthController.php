<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
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
        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::exists('users', 'email')->where('is_active', true),
            ],
        ], [
            'email.exists' => 'We could not find an active account with that email address.',
        ]);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Password reset link sent. Please check your email. The link expires in 1 hour.')
            : back()->withErrors(['email' => __($status)])->onlyInput('email');
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
            'email' => [
                'required',
                'email',
                Rule::exists('users', 'email')->where('is_active', true),
            ],
            'password' => ['required', 'string', 'confirmed', 'min:10', 'max:64'],
        ], [
            'email.exists' => 'We could not find an active account with that email address.',
            'password.min' => 'Password must be between 10 and 64 characters. Letters, numbers, symbols, and spaces are allowed.',
            'password.max' => 'Password must be between 10 and 64 characters. Letters, numbers, symbols, and spaces are allowed.',
        ]);

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
            : back()->withErrors(['email' => __($status)])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
