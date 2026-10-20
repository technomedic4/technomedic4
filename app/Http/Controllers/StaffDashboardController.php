<?php

namespace App\Http\Controllers;

use App\Support\HospitalStaffRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StaffDashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $roleName = DB::table('roles')->where('id', $user->role_id)->value('role_name');
        $role = HospitalStaffRole::tryFrom((string) $roleName);

        if ($role === null) {
            abort(403, 'Role akun tidak dikenali.');
        }

        $doctorId = null;

        if ($role === HospitalStaffRole::Doctor) {
            $doctorId = DB::table('doctors')->where('user_id', $user->id)->value('id');

            if ($doctorId === null) {
                abort(403, 'Akun dokter belum terhubung dengan profil dokter rumah sakit.');
            }
        }

        $appointmentsQuery = DB::table('appointments as appointments')
            ->join('doctor_schedules as schedules', 'schedules.id', '=', 'appointments.doctor_schedule_id')
            ->join('doctors as doctors', 'doctors.id', '=', 'schedules.doctor_id')
            ->whereDate('appointments.appointment_date', now()->toDateString());

        if ($doctorId !== null) {
            $appointmentsQuery->where('doctors.id', $doctorId);
        }

        if ($role === HospitalStaffRole::Finance) {
            $latestBills = DB::table('bills')
                ->join('patient_profiles as patients', 'patients.id', '=', 'bills.patient_id')
                ->select([
                    'bills.id',
                    'bills.bill_number',
                    'bills.total_amount',
                    'bills.amount_paid',
                    'bills.status',
                    'patients.full_name as patient_name',
                ])
                ->orderByDesc('bills.created_at')
                ->orderByDesc('bills.id')
                ->limit(8)
                ->get();

            return view('staff.dashboard', [
                'appointments' => collect(),
                'careUpdates' => collect(),
                'dateLabel' => now()->locale('id')->translatedFormat('l, d F Y'),
                'latestBills' => $latestBills,
                'stats' => [
                    [
                        'label' => 'Tagihan tercatat',
                        'value' => DB::table('bills')->count(),
                        'description' => 'Jumlah seluruh tagihan yang dibuat',
                    ],
                    [
                        'label' => 'Belum lunas',
                        'value' => DB::table('bills')->whereIn('status', ['Unpaid', 'Partially Paid'])->count(),
                        'description' => 'Tagihan dengan sisa pembayaran',
                    ],
                    [
                        'label' => 'Sudah lunas',
                        'value' => DB::table('bills')->where('status', 'Paid')->count(),
                        'description' => 'Tagihan yang telah dibayar penuh',
                    ],
                    [
                        'label' => 'Pembayaran hari ini',
                        'value' => (int) DB::table('bill_payments')
                            ->whereDate('paid_at', now()->toDateString())
                            ->sum('amount'),
                        'description' => 'Total penerimaan hari ini (Rp)',
                    ],
                ],
                'roleLabel' => $role->value,
                'userName' => Str::before($user->email, '@'),
            ]);
        }

        if ($role === HospitalStaffRole::Nurse) {
            $stats = [
                [
                    'label' => 'Pasien rawat inap',
                    'value' => DB::table('inpatient_admissions')->whereNull('discharged_at')->count(),
                    'description' => 'Pasien yang masih dirawat',
                ],
                [
                    'label' => 'Pembaruan kondisi hari ini',
                    'value' => DB::table('patient_care_updates')
                        ->whereNotNull('inpatient_admission_id')
                        ->whereDate('created_at', now()->toDateString())
                        ->count(),
                    'description' => 'Catatan perawatan yang dibuat hari ini',
                ],
            ];
        } else {
            $stats = [
                [
                    'label' => 'Jadwal hari ini',
                    'value' => (clone $appointmentsQuery)->count('appointments.id'),
                    'description' => $role === HospitalStaffRole::Doctor ? 'Pasien terdaftar pada jadwal Anda' : 'Seluruh pendaftaran hari ini',
                ],
                [
                    'label' => 'Menunggu',
                    'value' => (clone $appointmentsQuery)
                        ->whereIn('appointments.status', ['Pending', 'Confirmed'])
                        ->count('appointments.id'),
                    'description' => 'Menunggu atau telah dikonfirmasi',
                ],
                [
                    'label' => 'Sudah check-in',
                    'value' => (clone $appointmentsQuery)
                        ->where('appointments.status', 'Checked-In')
                        ->count('appointments.id'),
                    'description' => 'Pasien hadir di rumah sakit',
                ],
                [
                    'label' => 'Selesai',
                    'value' => (clone $appointmentsQuery)
                        ->where('appointments.status', 'Completed')
                        ->count('appointments.id'),
                    'description' => 'Kunjungan selesai hari ini',
                ],
            ];
        }

        $appointments = collect();
        $careUpdates = collect();

        if ($role === HospitalStaffRole::Nurse) {
            $careUpdates = DB::table('patient_care_updates as updates')
                ->join('inpatient_admissions as admissions', 'admissions.id', '=', 'updates.inpatient_admission_id')
                ->join('patient_profiles as patients', 'patients.id', '=', 'admissions.patient_id')
                ->leftJoin('users', 'users.id', '=', 'updates.recorded_by_user_id')
                ->whereNull('admissions.discharged_at')
                ->select([
                    'patients.full_name as patient_name',
                    'admissions.room_number',
                    'admissions.bed_number',
                    'updates.update_status',
                    'updates.notes',
                    'updates.temperature_celsius',
                    'updates.blood_pressure',
                    'updates.pulse_rate',
                    'updates.respiratory_rate',
                    'updates.oxygen_saturation',
                    'updates.created_at',
                    'users.email as recorded_by',
                ])
                ->orderByDesc('updates.created_at')
                ->orderByDesc('updates.id')
                ->limit(8)
                ->get();
        } else {
            $appointments = (clone $appointmentsQuery)
                ->join('patient_profiles as patients', 'patients.id', '=', 'appointments.patient_id')
                ->join('departments', 'departments.id', '=', 'doctors.department_id')
                ->join('doctor_schedules as appointment_schedules', 'appointment_schedules.id', '=', 'appointments.doctor_schedule_id')
                ->select([
                    'appointments.booking_code',
                    'appointments.queue_number',
                    'appointments.appointment_date',
                    'appointments.status',
                    'patients.full_name as patient_name',
                    'doctors.full_name as doctor_name',
                    'departments.name as department_name',
                    'schedules.start_time',
                ])
                ->orderBy('appointments.queue_number')
                ->limit(8)
                ->get();
        }

        return view('staff.dashboard', [
            'appointments' => $appointments,
            'careUpdates' => $careUpdates,
            'dateLabel' => now()->translatedFormat('l, d F Y'),
            'latestBills' => collect(),
            'roleLabel' => $role->value,
            'stats' => $stats,
            'userName' => $role === HospitalStaffRole::Doctor
                ? DB::table('doctors')->where('id', $doctorId)->value('full_name')
                : Str::before($user->email, '@'),
        ]);
    }
}
