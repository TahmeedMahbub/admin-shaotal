<?php

namespace App\Http\Controllers;

use App\Models\AdminUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:190'], 'password' => ['required', 'string', 'max:255']]);

        $email = Str::lower(trim($data['login']));

        $throttleKey = $email.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, config('admin.login_max_attempts'))) {
            throw ValidationException::withMessages(['login' => 'Too many attempts. Try again in '.RateLimiter::availableIn($throttleKey).' seconds.']);
        }

        $admin = AdminUser::where('email', $email)->orWhere('username', $email)->first();

        // Same message for unknown user, wrong password and inactive account: no account enumeration.
        if (! $admin || $admin->status !== 'active' || ! Hash::check($data['password'], $admin->password_hash)) {
            RateLimiter::hit($throttleKey, 60);
            throw ValidationException::withMessages(['login' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($throttleKey);
        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        $admin->forceFill(['last_login_at' => now()])->saveQuietly();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
