<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCoordinatorSingleSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->role === UserRole::COORDINATOR && $user->coordinator_session_hash && ! hash_equals($user->coordinator_session_hash, hash('sha256', $request->session()->get('coordinator_device_token', '')))) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->with('status', 'Sesi koordinator ini berakhir karena akun masuk dari perangkat lain.');
        }

        return $next($request);
    }
}
