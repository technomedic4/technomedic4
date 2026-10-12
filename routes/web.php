<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// home Page
// Home Page
Route::get('/', function () {
    return redirect('/home-page');
});

Route::get('/home-page', function () {
    $userName = Auth::check()
        ? DB::table('patient_profiles')->where('user_id', Auth::id())->value('full_name')
        : null;

    return view('home-page', ['userName' => $userName]);
});

// Login
Route::get('/login', function () {
    return view('login'); // Pastikan nama file view-mu 'login.blade.php'
});

// 2. Memproses data saat tombol login diklik (Metode POST)
Route::post('/login', [AuthController::class, 'login']);

// registrasi
Route::get('/registrasi', [AuthController::class, 'showRegister']);
Route::post('/registrasi', [AuthController::class, 'register']);

    // konsul
Route::get('/konsultasi', function () {
    return view('konsultasi');
});

// pemantauan
Route::get('/pantau', function () {
    return view('pantau');
});

// jadwal
Route::get('/jadwal', function () {
    return view('jadwal');
});
