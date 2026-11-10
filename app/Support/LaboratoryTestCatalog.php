<?php

namespace App\Support;

use Illuminate\Support\Collection;

final class LaboratoryTestCatalog
{
    /**
     * @return array<string, array{label: string, groups: array<string, array{label: string, tests: array<string, string>}>}>
     */
    public static function groups(): array
    {
        return [
            'blood' => [
                'label' => 'Pemeriksaan Darah',
                'groups' => [
                    'hematology' => [
                        'label' => 'Hematologi / Darah Lengkap',
                        'tests' => [
                            'hemoglobin' => 'Hemoglobin (Hb)',
                            'hematocrit' => 'Hematokrit (HCT)',
                            'erythrocytes' => 'Eritrosit (RBC)',
                            'leukocytes' => 'Leukosit (WBC)',
                            'platelets' => 'Trombosit (PLT)',
                            'mcv' => 'MCV',
                            'mch' => 'MCH',
                            'mchc' => 'MCHC',
                        ],
                    ],
                    'blood_chemistry' => [
                        'label' => 'Kimia Darah',
                        'tests' => [
                            'blood_glucose' => 'Glukosa darah',
                            'urea' => 'Ureum',
                            'creatinine' => 'Kreatinin',
                            'uric_acid' => 'Asam urat',
                            'total_cholesterol' => 'Kolesterol total',
                            'triglycerides' => 'Trigliserida',
                        ],
                    ],
                ],
            ],
            'urine' => [
                'label' => 'Pemeriksaan Urine',
                'groups' => [
                    'urine_physical' => [
                        'label' => 'Pemeriksaan Fisik',
                        'tests' => [
                            'urine_color' => 'Warna urine',
                            'urine_clarity' => 'Kejernihan',
                            'urine_specific_gravity' => 'Berat jenis (Specific Gravity)',
                            'urine_ph' => 'pH',
                        ],
                    ],
                    'urine_chemical' => [
                        'label' => 'Pemeriksaan Kimia',
                        'tests' => [
                            'urine_protein' => 'Protein',
                            'urine_glucose' => 'Glukosa',
                            'urine_ketone' => 'Keton',
                            'urine_bilirubin' => 'Bilirubin',
                            'urine_urobilinogen' => 'Urobilinogen',
                            'urine_blood' => 'Darah (Blood)',
                            'urine_nitrite' => 'Nitrit',
                            'urine_leukocyte_esterase' => 'Leukosit esterase',
                        ],
                    ],
                    'urine_microscopy' => [
                        'label' => 'Pemeriksaan Mikroskopis',
                        'tests' => [
                            'urine_erythrocytes' => 'Eritrosit',
                            'urine_leukocytes' => 'Leukosit',
                            'urine_epithelial_cells' => 'Epitel',
                            'urine_bacteria' => 'Bakteri',
                            'urine_crystals' => 'Kristal',
                            'urine_casts' => 'Silinder',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function tests(): array
    {
        $tests = [];

        foreach (self::groups() as $specimen) {
            foreach ($specimen['groups'] as $group) {
                $tests += $group['tests'];
            }
        }

        return $tests;
    }

    /**
     * @return array<string, string>
     */
    public static function testsForSpecimen(string $specimenCode): array
    {
        return collect(self::groups()[$specimenCode]['groups'])
            ->flatMap(fn (array $group): array => $group['tests'])
            ->all();
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<string, array{label: string, groups: array<string, array{label: string, tests: array<string, string}>}>
     */
    public static function requestedGroups(array $codes): array
    {
        $requestedGroups = [];

        foreach (self::groups() as $specimenCode => $specimen) {
            $groups = [];

            foreach ($specimen['groups'] as $groupCode => $group) {
                $tests = array_intersect_key($group['tests'], array_flip($codes));

                if ($tests !== []) {
                    $groups[$groupCode] = [
                        'label' => $group['label'],
                        'tests' => $tests,
                    ];
                }
            }

            if ($groups !== []) {
                $requestedGroups[$specimenCode] = [
                    'label' => $specimen['label'],
                    'groups' => $groups,
                ];
            }
        }

        return $requestedGroups;
    }

    /**
     * @param  Collection<int, object>  $results
     * @return array<string, array{label: string, groups: array<string, array{label: string, tests: Collection<int, object>}>}>
     */
    public static function resultGroups(Collection $results): array
    {
        $resultsByCode = $results->keyBy('parameter_code');
        $resultGroups = [];

        foreach (self::groups() as $specimenCode => $specimen) {
            $groups = [];

            foreach ($specimen['groups'] as $groupCode => $group) {
                $groupResults = collect(array_keys($group['tests']))
                    ->filter(fn (string $code): bool => $resultsByCode->has($code))
                    ->map(fn (string $code): object => $resultsByCode->get($code))
                    ->values();

                if ($groupResults->isNotEmpty()) {
                    $groups[$groupCode] = [
                        'label' => $group['label'],
                        'tests' => $groupResults,
                    ];
                }
            }

            if ($groups !== []) {
                $resultGroups[$specimenCode] = [
                    'label' => $specimen['label'],
                    'groups' => $groups,
                ];
            }
        }

        return $resultGroups;
    }
}
