<?php

namespace App\Http\Middleware;

use App\Support\HospitalStaffRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureHospitalStaff
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun tidak aktif. Hubungi administrator rumah sakit.',
            ]);
        }

        $roleName = DB::table('roles')->where('id', $user->role_id)->value('role_name');

        if (! in_array($roleName, HospitalStaffRole::values(), true)) {
            abort(Response::HTTP_FORBIDDEN, 'Akun ini tidak memiliki akses ke portal staf rumah sakit.');
        }

        return $next($request);
    }
}
