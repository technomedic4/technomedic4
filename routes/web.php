<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use Illuminate\Notifications\Notifiable;

// home Page
// Home Page
Route::get('/', function () {
    return redirect('/home-page');
});

Route::get('/home-page', function () {
    return view('home-page');
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

