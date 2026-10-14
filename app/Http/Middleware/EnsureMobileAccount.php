<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active, Response::HTTP_UNAUTHORIZED);

        $hasMobileRole = DB::table('roles')
            ->where('id', $user->role_id)
            ->whereIn('role_name', ['Pasien', 'Keluarga'])
            ->exists();

        abort_unless($hasMobileRole, Response::HTTP_FORBIDDEN, 'API ini hanya tersedia untuk akun pasien dan keluarga.');

        return $next($request);
    }
}
