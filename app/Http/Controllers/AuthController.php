<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\HospitalStaffRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

    public function login(Request $request): RedirectResponse
    {
        return $this->authenticate(
            $request,
            HospitalStaffRole::values(),
            route('staff.dashboard'),
            'Email atau password salah, atau akun tidak memiliki akses portal staf.',
        );
    }

    public function patientLogin(Request $request): RedirectResponse
    {
        return $this->authenticate(
            $request,
            ['Pasien'],
            route('patient.teleconsultations.index'),
            'Email atau password salah, atau akun tidak memiliki akses portal pasien.',
        );
    }

    /**
     * @param list<string> $allowedRoles
     */
    private function authenticate(
        Request $request,
        array $allowedRoles,
        string $redirectRoute,
        string $errorMessage,
    ): RedirectResponse {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $user = User::where('email', $credentials['email'])->first();
        $storedPassword = $user?->getAuthPassword();
        $passwordInfo = $storedPassword ? password_get_info($storedPassword) : ['algoName' => 'unknown'];

        $passwordMatches = match ($passwordInfo['algoName']) {
            'bcrypt' => Hash::check($credentials['password'], $storedPassword),
            'unknown' => $storedPassword !== null && hash_equals($storedPassword, $credentials['password']),
            default => password_verify($credentials['password'], $storedPassword),
        };

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
            if ($passwordInfo['algoName'] !== 'bcrypt') {
                $user->password_hash = Hash::make($credentials['password']);
                $user->save();
            }

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
