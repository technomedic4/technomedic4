<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MobilePatientAccess;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobilePatientController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $role = DB::table('roles')->where('id', $user->role_id)->value('role_name');
        $patientProfile = DB::table('patient_profiles')
            ->select([
                'id',
                'medical_record_number',
                'full_name',
                'phone_number',
                'gender',
                'birth_date',
            ])
            ->where('user_id', $user->id)
            ->first();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'role' => $role,
                'patient_profile' => $patientProfile,
            ],
        ]);
    }

    public function patients(Request $request, MobilePatientAccess $patientAccess): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $patientIds = $patientAccess->patientIds($user);

        $patients = DB::table('patient_profiles')
            ->leftJoin('family_relationships', function (JoinClause $join) use ($user): void {
                $join->on('family_relationships.patient_id', '=', 'patient_profiles.id')
                    ->where('family_relationships.family_user_id', $user->id)
                    ->where('family_relationships.status', 'Approved');
            })
            ->whereIn('patient_profiles.id', $patientIds)
            ->select([
                'patient_profiles.id',
                'patient_profiles.medical_record_number',
                'patient_profiles.full_name',
                'patient_profiles.phone_number',
                'patient_profiles.gender',
                'patient_profiles.birth_date',
            ])
            ->selectRaw(
                'CASE WHEN patient_profiles.user_id = ? THEN ? ELSE family_relationships.relationship_type END AS relationship',
                [$user->id, 'Diri sendiri'],
            )
            ->selectRaw(
                'CASE WHEN patient_profiles.user_id = ? THEN ? ELSE family_relationships.access_permission END AS access_permission',
                [$user->id, 'Full-Access'],
            )
            ->orderBy('patient_profiles.full_name')
            ->get();

        return response()->json(['data' => $patients]);
    }
}
