<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MobilePatientAccess
{
    /**
     * @return Collection<int, int>
     */
    public function patientIds(User $user): Collection
    {
        $patientIds = DB::table('patient_profiles')
            ->where('user_id', $user->id)
            ->pluck('id');

        $isFamily = DB::table('roles')
            ->where('id', $user->role_id)
            ->where('role_name', 'Keluarga')
            ->exists();

        if (! $isFamily) {
            return $patientIds->map(static fn ($id): int => (int) $id);
        }

        return $patientIds
            ->merge(
                DB::table('family_relationships')
                    ->where('family_user_id', $user->id)
                    ->where('status', 'Approved')
                    ->pluck('patient_id'),
            )
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();
    }

    public function canManage(User $user, int $patientId): bool
    {
        $ownsPatientProfile = DB::table('patient_profiles')
            ->where('id', $patientId)
            ->where('user_id', $user->id)
            ->exists();

        if ($ownsPatientProfile) {
            return true;
        }

        return DB::table('family_relationships')
            ->where('patient_id', $patientId)
            ->where('family_user_id', $user->id)
            ->where('status', 'Approved')
            ->where('access_permission', 'Full-Access')
            ->exists();
    }
}
