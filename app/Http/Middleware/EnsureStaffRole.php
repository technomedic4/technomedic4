<?php

namespace App\Http\Middleware;

use App\Support\HospitalStaffRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $roleName = DB::table('roles')
            ->where('id', $request->user()->role_id)
            ->value('role_name');

        if (! in_array($roleName, $roles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Akun ini tidak memiliki akses ke fitur ini.');
        }

        return $next($request);
    }
}
