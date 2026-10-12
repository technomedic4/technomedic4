<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsurePatient
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isPatient = DB::table('roles')
            ->where('id', $request->user()->role_id)
            ->where('role_name', 'Pasien')
            ->exists();

        abort_unless($isPatient, Response::HTTP_FORBIDDEN, 'Fitur ini hanya tersedia untuk akun pasien.');

        return $next($request);
    }
}
