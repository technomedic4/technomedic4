<?php

namespace App\Support;

enum PatientConditionStatus: string
{
    case Stable = 'Stabil';
    case Improving = 'Membaik';
    case Monitor = 'Perlu Pemantauan';
    case Worsening = 'Memburuk';
    case Critical = 'Kritis';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases(),
        );
    }
}
