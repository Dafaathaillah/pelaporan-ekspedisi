<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($request->user()->role === UserRole::COORDINATOR) {
            $deviceToken = bin2hex(random_bytes(32));
            $request->session()->put('coordinator_device_token', $deviceToken);
            $request->user()->forceFill(['coordinator_session_hash' => hash('sha256', $deviceToken)])->save();
        }

        return redirect()->intended($request->user()->homePath());
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user()?->role === UserRole::COORDINATOR && $request->session()->has('coordinator_device_token') && hash_equals($request->user()->coordinator_session_hash ?? '', hash('sha256', $request->session()->get('coordinator_device_token')))) {
            $request->user()->forceFill(['coordinator_session_hash' => null])->save();
        }
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
