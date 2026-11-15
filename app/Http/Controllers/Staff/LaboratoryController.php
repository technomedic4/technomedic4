<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Support\LaboratoryTestCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LaboratoryController extends Controller
{
    public function index(Request $request): View
    {
        return $this->requestList($request);
    }

    public function bloodRequests(Request $request): View
    {
        return $this->requestList($request, 'blood');
    }

    public function urineRequests(Request $request): View
    {
        return $this->requestList($request, 'urine');
    }

    private function requestList(Request $request, ?string $specimen = null): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:Pending,In Progress,Completed'],
        ]);
        $search = trim($validated['search'] ?? '');
        $status = $validated['status'] ?? '';

        $orders = $this->laboratoryOrders();
        if ($specimen !== null) {
            $this->filterBySpecimen($orders, $specimen);
        }
        if ($search !== '') {
            $orders->where(function (Builder $query) use ($search): void {
                $query->where('patients.full_name', 'like', "%{$search}%")
                    ->orWhere('patients.medical_record_number', 'like', "%{$search}%")
                    ->orWhere('laboratory_orders.requested_tests', 'like', "%{$search}%");
            });
        }
        if ($status !== '') {
            $orders->where('laboratory_orders.status', $status);
        }

        $labOrders = $orders
            ->orderByDesc('laboratory_orders.status')
            ->orderByDesc('laboratory_orders.created_at')
            ->orderByDesc('laboratory_orders.id')
            ->paginate(15)
            ->withQueryString();

        $statistics = DB::table('laboratory_orders')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END), 0) as pending")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END), 0) as in_progress")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END), 0) as completed")
            ->first();

        $specimenCounts = [
            'blood' => $this->filterBySpecimen($this->laboratoryOrders(), 'blood')->count('laboratory_orders.id'),
            'urine' => $this->filterBySpecimen($this->laboratoryOrders(), 'urine')->count('laboratory_orders.id'),
        ];
        $orderSpecimens = [];
        foreach ($labOrders as $order) {
            $codes = $order->requested_test_codes
                ? json_decode($order->requested_test_codes, true, 512, JSON_THROW_ON_ERROR)
                : [];
            $orderSpecimens[$order->id] = $this->specimensForOrder($codes, $order->requested_tests);
        }

        return view('staff.laboratory.requests.index', compact(
            'labOrders',
            'statistics',
            'specimenCounts',
            'orderSpecimens',
            'search',
            'status',
            'specimen',
        ));
    }

    public function show(Request $request, int $labOrder): View
    {
        $validated = $request->validate([
            'specimen' => ['nullable', 'in:blood,urine'],
        ]);
        $order = $this->laboratoryOrders()
            ->where('laboratory_orders.id', $labOrder)
            ->first();

        abort_if($order === null, 404);

        $results = DB::table('laboratory_results')
            ->where('laboratory_order_id', $labOrder)
            ->orderBy('sort_order')
            ->get();
        $resultGroups = LaboratoryTestCatalog::resultGroups($results);

        $requestedCodes = $order->requested_test_codes
            ? json_decode($order->requested_test_codes, true, 512, JSON_THROW_ON_ERROR)
            : [];
        $requestedTestGroups = LaboratoryTestCatalog::requestedGroups($requestedCodes);
        $orderSpecimens = $this->specimensForOrder($requestedCodes, $order->requested_tests);
        $specimen = $validated['specimen'] ?? $orderSpecimens[0] ?? 'blood';
        abort_if(! in_array($specimen, $orderSpecimens, true), 404);

        $specimenCodes = $requestedCodes === []
            ? []
            : array_values(array_intersect(
                array_keys(LaboratoryTestCatalog::testsForSpecimen($specimen)),
                $requestedCodes
            ));
        $specimenTestGroups = $requestedCodes === []
            ? []
            : LaboratoryTestCatalog::requestedGroups($specimenCodes);
        $specimenResults = $results
            ->whereIn('parameter_code', $specimenCodes)
            ->values();
        $specimenResultGroups = LaboratoryTestCatalog::resultGroups($specimenResults);
        $specimenComplete = $specimenCodes !== []
            && count($specimenCodes) === $specimenResults->count();
        $remainingSpecimens = array_values(array_filter(
            $orderSpecimens,
            fn (string $code): bool => $code !== $specimen
                && ! $this->specimenIsComplete($code, $requestedCodes, $results)
        ));

        return view('staff.laboratory.requests.show', compact(
            'order',
            'results',
            'resultGroups',
            'requestedTestGroups',
            'specimen',
            'specimenTestGroups',
            'specimenResultGroups',
            'specimenComplete',
            'remainingSpecimens',
        ));
    }

    public function start(Request $request, int $labOrder): RedirectResponse
    {
        $validated = $request->validate([
            'specimen' => ['nullable', 'in:blood,urine'],
        ]);

        $specimen = DB::transaction(function () use ($labOrder, $validated): string {
            $order = DB::table('laboratory_orders')
                ->where('id', $labOrder)
                ->lockForUpdate()
                ->first();

            abort_if($order === null, 404);
            $requestedCodes = $order->requested_test_codes
                ? json_decode($order->requested_test_codes, true, 512, JSON_THROW_ON_ERROR)
                : [];
            $specimen = $validated['specimen']
                ?? $this->specimensForOrder($requestedCodes, $order->requested_tests)[0]
                ?? 'blood';
            abort_unless(
                in_array($specimen, $this->specimensForOrder($requestedCodes, $order->requested_tests), true),
                404
            );
            if (! in_array($order->status, ['Pending', 'In Progress'], true)) {
                throw ValidationException::withMessages([
                    'lab_order' => 'Permintaan ini sudah selesai.',
                ]);
            }

            if ($order->status === 'Pending') {
                DB::table('laboratory_orders')
                    ->where('id', $labOrder)
                    ->update([
                        'status' => 'In Progress',
                        'updated_at' => now(),
                    ]);
            }

            return $specimen;
        });

        return redirect()
            ->route('staff.laboratory.requests.show', ['labOrder' => $labOrder, 'specimen' => $specimen])
            ->with('success', 'Pemeriksaan dimulai.');
    }

    public function storeResult(Request $request, int $labOrder): RedirectResponse
    {
        $completed = DB::transaction(function () use ($request, $labOrder): bool {
            $order = DB::table('laboratory_orders')
                ->where('id', $labOrder)
                ->lockForUpdate()
                ->first();

            abort_if($order === null, 404);

            if ($order->status !== 'In Progress') {
                throw ValidationException::withMessages([
                    'lab_order' => 'Pemeriksaan harus dimulai sebelum hasil dapat dikirim.',
                ]);
            }

            $requestedCodes = $order->requested_test_codes
                ? json_decode($order->requested_test_codes, true, 512, JSON_THROW_ON_ERROR)
                : [];
            $validated = $request->validate([
                'specimen' => [$requestedCodes === [] ? 'nullable' : 'required', 'in:blood,urine'],
            ]);
            $specimen = $validated['specimen'] ?? 'blood';
            abort_unless(
                in_array($specimen, $this->specimensForOrder($requestedCodes, $order->requested_tests), true),
                404
            );
            $specimenCodes = $requestedCodes === []
                ? []
                : array_values(array_intersect(
                    array_keys(LaboratoryTestCatalog::testsForSpecimen($specimen)),
                    $requestedCodes
                ));
            if ($requestedCodes !== [] && $specimenCodes === []) {
                throw ValidationException::withMessages([
                    'specimen' => 'Tidak ada parameter dari jenis pemeriksaan ini pada rujukan.',
                ]);
            }
            $validationRules = [
                'result_notes' => ['nullable', 'string', 'max:3000'],
            ];
            if ($specimenCodes === []) {
                $validationRules['results'] = ['required', 'string', 'max:10000'];
            } else {
                $validationRules['parameter_results'] = ['required', 'array'];
                foreach ($specimenCodes as $code) {
                    $validationRules["parameter_results.{$code}.value"] = ['required', 'string', 'max:100'];
                    $validationRules["parameter_results.{$code}.unit"] = ['nullable', 'string', 'max:40'];
                    $validationRules["parameter_results.{$code}.reference_range"] = ['nullable', 'string', 'max:100'];
                    $validationRules["parameter_results.{$code}.interpretation"] = $specimen === 'blood'
                        ? ['required', 'in:Low,Normal,High']
                        : ['nullable', 'in:Low,Normal,High'];
                }
            }
            $validated = $request->validate($validationRules);

            if ($specimenCodes !== []) {
                $knownCodes = array_keys(LaboratoryTestCatalog::tests());
                abort_if(array_diff($requestedCodes, $knownCodes) !== [], 422, 'Kode pemeriksaan tidak dikenal.');
                $submittedCodes = array_keys($validated['parameter_results']);
                if (array_diff($submittedCodes, $specimenCodes) !== [] || array_diff($specimenCodes, $submittedCodes) !== []) {
                    throw ValidationException::withMessages([
                        'parameter_results' => 'Hasil harus diisi untuk semua parameter pada formulir pemeriksaan ini.',
                    ]);
                }

                foreach ($specimenCodes as $index => $code) {
                    $parameter = $validated['parameter_results'][$code];
                    DB::table('laboratory_results')->updateOrInsert(
                        [
                            'laboratory_order_id' => $labOrder,
                            'parameter_code' => $code,
                        ],
                        [
                            'parameter_name' => LaboratoryTestCatalog::tests()[$code],
                            'result_value' => $parameter['value'],
                            'unit' => $parameter['unit'] ?? null,
                            'reference_range' => $parameter['reference_range'] ?? null,
                            'interpretation' => $parameter['interpretation'] ?? null,
                            'sort_order' => $index,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }

            $savedCodes = DB::table('laboratory_results')
                ->where('laboratory_order_id', $labOrder)
                ->pluck('parameter_code')
                ->all();
            $allRequestedResultsSaved = $requestedCodes !== []
                && array_diff($requestedCodes, $savedCodes) === [];

            $completed = $requestedCodes === [] || $allRequestedResultsSaved;
            DB::table('laboratory_orders')
                ->where('id', $labOrder)
                ->update([
                    'status' => $completed ? 'Completed' : 'In Progress',
                    'results' => $validated['results'] ?? null,
                    'result_notes' => $validated['result_notes'] ?? $order->result_notes,
                    'resulted_by_user_id' => $request->user()->id,
                    'resulted_at' => now(),
                    'updated_at' => now(),
                ]);

            return $completed;
        });

        return redirect()
            ->route('staff.laboratory.requests.show', [
                'labOrder' => $labOrder,
                'specimen' => $request->input('specimen', 'blood'),
            ])
            ->with(
                'success',
                $completed
                    ? 'Hasil laboratorium lengkap berhasil disimpan dan telah tersedia untuk dokter perujuk.'
                    : 'Hasil sampel berhasil disimpan. Sampel lain masih perlu diperiksa sebelum hasil lengkap tersedia untuk dokter.',
            );
    }

    private function filterBySpecimen(Builder $query, string $specimen): Builder
    {
        $codes = array_keys(LaboratoryTestCatalog::testsForSpecimen($specimen));
        $labels = array_intersect_key(LaboratoryTestCatalog::tests(), array_flip($codes));

        return $query->where(function (Builder $query) use ($specimen, $labels): void {
            $query->where(function (Builder $query) use ($labels): void {
                foreach ($labels as $label) {
                    $query->orWhere('laboratory_orders.requested_tests', 'like', "%{$label}%");
                }
            });

            $query->orWhere(function (Builder $query) use ($specimen): void {
                $query->whereNull('laboratory_orders.requested_test_codes');

                if ($specimen === 'urine') {
                    $query->where('laboratory_orders.requested_tests', 'like', '%urin%');
                } else {
                    $query->where('laboratory_orders.requested_tests', 'not like', '%urin%')
                        ->where(function (Builder $query): void {
                            $query->where('laboratory_orders.requested_tests', 'like', '%darah%')
                                ->orWhere('laboratory_orders.requested_tests', 'like', '%widal%');
                        });
                }
            });
        });
    }

    /**
     * @param  array<int, string>  $requestedCodes
     * @return array<int, string>
     */
    private function specimensForOrder(array $requestedCodes, string $legacyTests): array
    {
        if ($requestedCodes === []) {
            return [str_contains(mb_strtolower($legacyTests), 'urin') ? 'urine' : 'blood'];
        }

        return array_keys(LaboratoryTestCatalog::requestedGroups($requestedCodes));
    }

    /**
     * @param  array<int, string>  $requestedCodes
     * @param  Collection<int, object>  $results
     */
    private function specimenIsComplete(string $specimen, array $requestedCodes, $results): bool
    {
        $codes = array_intersect(array_keys(LaboratoryTestCatalog::testsForSpecimen($specimen)), $requestedCodes);

        return $codes !== []
            && count($codes) === $results->whereIn('parameter_code', $codes)->count();
    }

    private function laboratoryOrders(): Builder
    {
        return DB::table('laboratory_orders')
            ->join('doctor_consultations as consultations', 'consultations.id', '=', 'laboratory_orders.consultation_id')
            ->join('appointments', 'appointments.id', '=', 'consultations.appointment_id')
            ->join('patient_profiles as patients', 'patients.id', '=', 'laboratory_orders.patient_id')
            ->join('doctors as referring_doctors', 'referring_doctors.id', '=', 'laboratory_orders.referring_doctor_id')
            ->join('departments as referring_departments', 'referring_departments.id', '=', 'referring_doctors.department_id')
            ->leftJoin('users as lab_users', 'lab_users.id', '=', 'laboratory_orders.resulted_by_user_id')
            ->select([
                'laboratory_orders.*',
                'appointments.appointment_date',
                'appointments.booking_code',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
                'patients.gender',
                'patients.birth_date',
                'referring_doctors.full_name as referring_doctor_name',
                'referring_departments.name as referring_department_name',
                'lab_users.email as resulted_by_email',
            ]);
    }
}
