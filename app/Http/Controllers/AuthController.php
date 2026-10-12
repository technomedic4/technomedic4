<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    // Tampilkan halaman registrasi
    public function showRegister()
    {
        return view('registrasi');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|string|email|max:100|unique:users,email',
            'phone_number' => 'required|string|max:20|unique:users,phone_number',
            'gender' => 'required|in:L,P',
            'birth_date' => 'required|date|before_or_equal:today',
            'password' => 'required|string|min:6',
        ]);

        $patientRoleId = DB::table('roles')->where('role_name', 'Pasien')->value('id');

        if (!$patientRoleId) {
            return back()->withInput()->withErrors([
                'role' => 'Role Pasien belum tersedia. Hubungi administrator.',
            ]);
        }

        DB::transaction(function () use ($validated, $patientRoleId) {
            $user = User::create([
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'password_hash' => Hash::make($validated['password']),
                'role_id' => $patientRoleId,
                'is_active' => 1,
            ]);

            DB::table('patient_profiles')->insert([
                'user_id' => $user->id,
                'full_name' => $validated['name'],
                'gender' => $validated['gender'],
                'birth_date' => $validated['birth_date'],
            ]);
        });

        return redirect(url('/login'))->with('success', 'Registrasi berhasil! Silakan login.');
    }


    public function showLogin()
    {
        return view('login');
    }

  
    public function login(Request $request)
    {
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

        if ($user && $passwordMatches) {
            if ($passwordInfo['algoName'] !== 'bcrypt') {
                $user->password_hash = Hash::make($credentials['password']);
                $user->save();
            }

            Auth::login($user);
            $request->session()->regenerate();

            // Arahkan ke halaman dashboard atau beranda setelah berhasil login
            return redirect()->intended('/home-page')->with('success', 'Berhasil login!');
        }

        // 3. Jika gagal, kembalikan ke halaman login dengan pesan error
        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }
}
