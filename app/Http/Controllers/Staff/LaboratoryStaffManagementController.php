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

class LaboratoryStaffManagementController extends Controller
{
    public function index(): View
    {
        $staff = DB::table('users')
            ->join('roles', 'roles.id', '=', 'users.role_id')
            ->where('roles.role_name', HospitalStaffRole::Laboratory->value)
            ->select(['users.email', 'users.phone_number', 'users.is_active'])
            ->orderBy('users.email')
            ->get();

        return view('staff.management.laboratory.index', compact('staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')],
            'phone_number' => ['required', 'string', 'max:20', Rule::unique('users', 'phone_number')],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated): void {
            $laboratoryRoleId = DB::table('roles')
                ->where('role_name', HospitalStaffRole::Laboratory->value)
                ->value('id');

            if ($laboratoryRoleId === null) {
                throw new \RuntimeException('Role laboratorium belum tersedia. Jalankan migrasi laboratorium terlebih dahulu.');
            }

            DB::table('users')->insert([
                'role_id' => $laboratoryRoleId,
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'password_hash' => Hash::make($validated['password']),
                'is_active' => true,
            ]);
        });

        return redirect()
            ->route('staff.management.laboratory.index')
            ->with('success', 'Akun laboratorium berhasil dibuat dan dapat digunakan untuk masuk ke portal staf.');
    }
}
