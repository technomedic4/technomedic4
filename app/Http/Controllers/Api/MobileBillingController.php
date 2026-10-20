<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MobilePatientAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MobileBillingController extends Controller
{
    public function index(Request $request, MobilePatientAccess $patientAccess): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $patientIds = $patientAccess->patientIds($user);

        if (isset($validated['patient_id'])) {
            $patientId = (int) $validated['patient_id'];
            abort_unless($patientIds->contains($patientId), JsonResponse::HTTP_NOT_FOUND);
            $patientIds = collect([$patientId]);
        }

        $bills = DB::table('bills')
            ->join('patient_profiles as patients', 'patients.id', '=', 'bills.patient_id')
            ->whereIn('bills.patient_id', $patientIds)
            ->select([
                'bills.id',
                'bills.bill_number',
                'bills.patient_id',
                'bills.total_amount',
                'bills.amount_paid',
                'bills.status',
                'bills.created_at',
                'patients.full_name as patient_name',
            ])
            ->orderByDesc('bills.created_at')
            ->orderByDesc('bills.id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        $billIds = $bills->getCollection()->pluck('id');
        $itemsByBill = DB::table('bill_items')
            ->whereIn('bill_id', $billIds)
            ->orderBy('id')
            ->get()
            ->groupBy('bill_id');

        $bills->getCollection()->transform(function (object $bill) use ($itemsByBill): object {
            $bill->id = (int) $bill->id;
            $bill->patient_id = (int) $bill->patient_id;
            $bill->total_amount = (int) $bill->total_amount;
            $bill->amount_paid = (int) $bill->amount_paid;
            $bill->balance_due = $bill->status === 'Cancelled'
                ? 0
                : $bill->total_amount - $bill->amount_paid;
            $bill->items = $itemsByBill->get($bill->id, new Collection)
                ->map(static fn (object $item): array => [
                    'item_name' => $item->item_name,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (int) $item->unit_price,
                    'subtotal' => (int) $item->quantity * (int) $item->unit_price,
                ])
                ->values();

            return $bill;
        });

        return response()->json(['data' => $bills]);
    }
}
