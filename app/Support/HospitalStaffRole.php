<?php

namespace App\Support;

enum HospitalStaffRole: string
{
    case Admin = 'Admin';
    case Receptionist = 'Resepsionis';
    case Nurse = 'Perawat';
    case Doctor = 'Dokter';
    case Finance = 'Keuangan';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }
}
