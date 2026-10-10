<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    // Tampilkan halaman registrasi
    public function showRegister()
    {
        return view('registrasi');
    }

    public function register(Request $request)
    {
       $request->validate([
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        User::create([
            'email' => $request->email,
            'password_hash' => Hash::make($request->password),
            'role_id' => 1, // Sesuaikan dengan id role yang ada di database kelompokmu
            'is_active' => 1,
        ]);

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

        $user = User::where('email', $request->email)->first();

        if (Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']])) {
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
