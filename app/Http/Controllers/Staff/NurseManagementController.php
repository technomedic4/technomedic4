<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\HospitalStaffRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class NurseManagementController extends Controller
{
    public function index(): View
    {
        $nurses = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.role_name', HospitalStaffRole::Nurse->value)
            ->select(['users.email', 'users.phone_number', 'users.is_active'])
            ->orderBy('users.email')
            ->get();

        return view('staff.management.nurses.index', compact('nurses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')],
            'phone_number' => ['required', 'string', 'max:20', Rule::unique('users', 'phone_number')],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated): void {
            $nurseRoleId = DB::table('roles')
                ->where('role_name', HospitalStaffRole::Nurse->value)
                ->value('id');

            if ($nurseRoleId === null) {
                throw new \RuntimeException('Role perawat belum tersedia. Jalankan migrasi role staf terlebih dahulu.');
            }

            DB::table('users')->insert([
                'role_id' => $nurseRoleId,
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'password_hash' => Hash::make($validated['password']),
                'is_active' => true,
            ]);
        });

        return redirect()
            ->route('staff.management.nurses.index')
            ->with('success', 'Akun perawat berhasil dibuat dan dapat digunakan untuk masuk ke portal staf.');
    }
}
