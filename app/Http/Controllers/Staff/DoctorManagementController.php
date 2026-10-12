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

class DoctorManagementController extends Controller
{
    public function index(): View
    {
        $doctors = DB::table('doctors')
            ->join('users', 'users.id', '=', 'doctors.user_id')
            ->join('departments', 'departments.id', '=', 'doctors.department_id')
            ->select([
                'doctors.full_name',
                'doctors.specialization',
                'doctors.str_number',
                'users.email',
                'users.phone_number',
                'departments.name as department_name',
            ])
            ->orderBy('doctors.full_name')
            ->get();

        $departments = DB::table('departments')
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get();

        return view('staff.management.doctors.index', compact('doctors', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:100', Rule::unique('users', 'email')],
            'phone_number' => ['required', 'string', 'max:20', Rule::unique('users', 'phone_number')],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'specialization' => ['required', 'string', 'max:100'],
            'str_number' => ['required', 'string', 'max:100', Rule::unique('doctors', 'str_number')],
        ]);

        DB::transaction(function () use ($validated): void {
            $doctorRoleId = DB::table('roles')
                ->where('role_name', HospitalStaffRole::Doctor->value)
                ->value('id');

            if ($doctorRoleId === null) {
                throw new \RuntimeException('Role dokter belum tersedia. Jalankan seeder role staf terlebih dahulu.');
            }

            $userId = DB::table('users')->insertGetId([
                'role_id' => $doctorRoleId,
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'password_hash' => Hash::make($validated['password']),
                'is_active' => true,
            ]);

            DB::table('doctors')->insert([
                'user_id' => $userId,
                'department_id' => $validated['department_id'],
                'full_name' => $validated['full_name'],
                'specialization' => $validated['specialization'],
                'str_number' => $validated['str_number'],
            ]);
        });

        return redirect()
            ->route('staff.management.doctors.index')
            ->with('success', 'Akun dokter berhasil dibuat dan dapat digunakan untuk masuk ke portal staf.');
    }
}
