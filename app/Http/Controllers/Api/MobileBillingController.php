<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MobilePatientAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
        $submissionsByBill = DB::table('mobile_payment_submissions')
            ->whereIn('bill_id', $billIds)
            ->select([
                'id',
                'bill_id',
                'amount',
                'payment_method',
                'status',
                'reference',
                'review_note',
                'created_at',
                'reviewed_at',
            ])
            ->orderByDesc('id')
            ->get()
            ->groupBy('bill_id');

        $bills->getCollection()->transform(function (object $bill) use ($itemsByBill, $submissionsByBill): object {
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
            $bill->payment_submissions = $submissionsByBill
                ->get($bill->id, new Collection)
                ->values();

            return $bill;
        });

        return response()->json([
            'data' => $bills,
            'payment_instructions' => [
                'bank_name' => config('services.billing.bank_name'),
                'bank_account' => config('services.billing.bank_account'),
                'bank_account_name' => config('services.billing.bank_account_name'),
                'qris_merchant_name' => config('services.billing.qris_merchant_name'),
                'qris_image_url' => config('services.billing.qris_image_url'),
            ],
        ]);
    }

    public function submitPayment(
        Request $request,
        int $bill,
        MobilePatientAccess $patientAccess,
    ): JsonResponse {
        $validated = $request->validate([
            'payment_method' => ['required', 'in:Transfer,QRIS'],
            'reference' => ['nullable', 'string', 'max:100'],
            'proof' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $billRecord = DB::table('bills')->where('id', $bill)->first();
        abort_if($billRecord === null, JsonResponse::HTTP_NOT_FOUND);
        abort_unless(
            $patientAccess->patientIds($user)->contains((int) $billRecord->patient_id),
            JsonResponse::HTTP_NOT_FOUND,
        );
        abort_unless(
            $patientAccess->canManage($user, (int) $billRecord->patient_id),
            JsonResponse::HTTP_FORBIDDEN,
            'Akun keluarga memerlukan izin penuh untuk mengajukan pembayaran.',
        );

        $proofPath = $validated['proof']->store('payment-proofs', 'local');
        if ($proofPath === false) {
            throw new \RuntimeException('Bukti pembayaran gagal disimpan.');
        }

        try {
            $submissionId = DB::transaction(function () use (
                $bill,
                $user,
                $validated,
                $proofPath,
            ): int {
                $lockedBill = DB::table('bills')
                    ->where('id', $bill)
                    ->lockForUpdate()
                    ->first();
                abort_if($lockedBill === null, JsonResponse::HTTP_NOT_FOUND);

                if (in_array($lockedBill->status, ['Paid', 'Cancelled'], true)) {
                    throw ValidationException::withMessages([
                        'bill' => 'Tagihan ini tidak menerima pengajuan pembayaran.',
                    ]);
                }

                $pendingExists = DB::table('mobile_payment_submissions')
                    ->where('bill_id', $bill)
                    ->where('status', 'Pending')
                    ->exists();
                if ($pendingExists) {
                    throw ValidationException::withMessages([
                        'payment' => 'Bukti pembayaran tagihan ini sedang menunggu verifikasi.',
                    ]);
                }

                $balanceDue = (int) $lockedBill->total_amount - (int) $lockedBill->amount_paid;
                if ($balanceDue < 1) {
                    throw ValidationException::withMessages([
                        'payment' => 'Tidak ada sisa tagihan yang perlu dibayar.',
                    ]);
                }

                return (int) DB::table('mobile_payment_submissions')->insertGetId([
                    'bill_id' => $bill,
                    'submitted_by_user_id' => $user->id,
                    'amount' => $balanceDue,
                    'payment_method' => $validated['payment_method'],
                    'reference' => $validated['reference'] ?? null,
                    'proof_path' => $proofPath,
                    'status' => 'Pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($proofPath);
            throw $exception;
        }

        return response()->json([
            'message' => 'Bukti pembayaran berhasil dikirim dan menunggu verifikasi petugas.',
            'data' => ['id' => $submissionId, 'status' => 'Pending'],
        ], JsonResponse::HTTP_CREATED);
    }
}
