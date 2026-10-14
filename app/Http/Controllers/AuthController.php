<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\HospitalStaffRole;
use App\Support\UserPasswordVerifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('staff.login');
    }

    public function showPatientLogin(): View
    {
        return view('patient.login');
    }

    public function login(Request $request, UserPasswordVerifier $passwordVerifier): RedirectResponse
    {
        return $this->authenticate(
            $request,
            $passwordVerifier,
            HospitalStaffRole::values(),
            route('staff.dashboard'),
            'Email atau password salah, atau akun tidak memiliki akses portal staf.',
        );
    }

    public function patientLogin(Request $request, UserPasswordVerifier $passwordVerifier): RedirectResponse
    {
        return $this->authenticate(
            $request,
            $passwordVerifier,
            ['Pasien'],
            route('patient.teleconsultations.index'),
            'Email atau password salah, atau akun tidak memiliki akses portal pasien.',
        );
    }

    /**
     * @param  list<string>  $allowedRoles
     */
    private function authenticate(
        Request $request,
        UserPasswordVerifier $passwordVerifier,
        array $allowedRoles,
        string $redirectRoute,
        string $errorMessage,
    ): RedirectResponse {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $user = User::where('email', $credentials['email'])->first();
        $passwordMatches = $user !== null
            && $passwordVerifier->verifyAndUpgrade($user, $credentials['password']);

        $roleName = $user
            ? DB::table('roles')->where('id', $user->role_id)->value('role_name')
            : null;

        $hasPatientProfile = $roleName !== 'Pasien'
            || DB::table('patient_profiles')->where('user_id', $user?->id)->exists();

        if (
            $user
            && $passwordMatches
            && $user->is_active
            && $hasPatientProfile
            && in_array($roleName, $allowedRoles, true)
        ) {
            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended($redirectRoute);
        }

        return back()->withErrors([
            'email' => $errorMessage,
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        $roleName = DB::table('roles')
            ->where('id', $request->user()->role_id)
            ->value('role_name');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $loginRoute = $roleName === 'Pasien' ? 'patient.login' : 'login';

        return redirect()->route($loginRoute)->with('success', 'Anda berhasil keluar.');
    }
}
