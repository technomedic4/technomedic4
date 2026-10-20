<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    private const STATUSES = ['Unpaid', 'Partially Paid', 'Paid', 'Cancelled'];

    private const PAYMENT_METHODS = ['Cash', 'Transfer', 'QRIS', 'Debit Card', 'Credit Card', 'Other'];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $status = $validated['status'] ?? '';

        $bills = DB::table('bills')
            ->join('patient_profiles as patients', 'patients.id', '=', 'bills.patient_id')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('patients.full_name', 'like', '%'.$search.'%')
                        ->orWhere('patients.medical_record_number', 'like', '%'.$search.'%')
                        ->orWhere('bills.bill_number', 'like', '%'.$search.'%');
                });
            })
            ->when($status !== '', fn ($query) => $query->where('bills.status', $status))
            ->select([
                'bills.id',
                'bills.bill_number',
                'bills.total_amount',
                'bills.amount_paid',
                'bills.status',
                'bills.created_at',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
            ])
            ->orderByDesc('bills.created_at')
            ->orderByDesc('bills.id')
            ->paginate(15)
            ->withQueryString();

        return view('staff.billing.index', [
            'bills' => $bills,
            'roleLabel' => $this->staffRoleLabel($request),
            'search' => $search,
            'status' => $status,
            'statuses' => self::STATUSES,
        ]);
    }

    public function create(Request $request): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));

        $patients = DB::table('patient_profiles')
            ->select(['id', 'full_name', 'medical_record_number'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('full_name', 'like', '%'.$search.'%')
                        ->orWhere('medical_record_number', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('full_name')
            ->limit(100)
            ->get();

        return view('staff.billing.form', [
            'bill' => null,
            'patients' => $patients,
            'roleLabel' => $this->staffRoleLabel($request),
            'search' => $search,
            'formItems' => [['item_name' => '', 'quantity' => 1, 'unit_price' => '']],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->billRules());
        $items = $this->normalizeItems($validated['items']);
        $totalAmount = $this->totalAmount($items);

        $billId = DB::transaction(function () use ($request, $validated, $items, $totalAmount): int {
            $now = now();
            $billId = DB::table('bills')->insertGetId([
                'bill_number' => 'INV-'.$now->format('Ymd').'-'.Str::upper(Str::random(8)),
                'patient_id' => $validated['patient_id'],
                'total_amount' => $totalAmount,
                'amount_paid' => 0,
                'status' => 'Unpaid',
                'created_by_user_id' => $request->user()->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->replaceItems($billId, $items, $now);

            return (int) $billId;
        });

        return redirect()
            ->route('staff.billing.show', $billId)
            ->with('success', 'Tagihan berhasil dibuat.');
    }

    public function show(Request $request, int $bill): View
    {
        return view('staff.billing.show', [
            'bill' => $this->loadBill($bill),
            'paymentMethods' => self::PAYMENT_METHODS,
            'roleLabel' => $this->staffRoleLabel($request),
        ]);
    }

    public function edit(Request $request, int $bill): View
    {
        $billRecord = $this->loadBill($bill);
        $this->ensureEditable($billRecord);

        $patients = DB::table('patient_profiles')
            ->select(['id', 'full_name', 'medical_record_number'])
            ->orderBy('full_name')
            ->limit(500)
            ->get();
        if (! $patients->contains('id', $billRecord->patient_id)) {
            $patients->prepend(DB::table('patient_profiles')
                ->select(['id', 'full_name', 'medical_record_number'])
                ->where('id', $billRecord->patient_id)
                ->first());
        }

        return view('staff.billing.form', [
            'bill' => $billRecord,
            'patients' => $patients,
            'roleLabel' => $this->staffRoleLabel($request),
            'search' => '',
            'formItems' => $billRecord->items->map(static fn (object $item): array => [
                'item_name' => $item->item_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])->all(),
        ]);
    }

    public function update(Request $request, int $bill): RedirectResponse
    {
        $validated = $request->validate($this->billRules());
        $items = $this->normalizeItems($validated['items']);
        $totalAmount = $this->totalAmount($items);

        DB::transaction(function () use ($bill, $validated, $items, $totalAmount): void {
            $billRecord = DB::table('bills')
                ->where('id', $bill)
                ->lockForUpdate()
                ->first();

            abort_if($billRecord === null, 404);
            $this->ensureEditable($billRecord);

            if ($totalAmount < (int) $billRecord->amount_paid) {
                throw ValidationException::withMessages([
                    'items' => 'Total tagihan tidak boleh lebih kecil dari pembayaran yang sudah diterima.',
                ]);
            }

            if ((int) $billRecord->amount_paid > 0 && (int) $validated['patient_id'] !== (int) $billRecord->patient_id) {
                throw ValidationException::withMessages([
                    'patient_id' => 'Pasien tidak dapat diubah setelah pembayaran tercatat.',
                ]);
            }

            $status = (int) $billRecord->amount_paid === $totalAmount
                ? 'Paid'
                : ((int) $billRecord->amount_paid > 0 ? 'Partially Paid' : 'Unpaid');
            $now = now();

            DB::table('bills')
                ->where('id', $bill)
                ->update([
                    'patient_id' => $validated['patient_id'],
                    'total_amount' => $totalAmount,
                    'status' => $status,
                    'updated_at' => $now,
                ]);

            DB::table('bill_items')->where('bill_id', $bill)->delete();
            $this->replaceItems($bill, $items, $now);
        });

        return redirect()
            ->route('staff.billing.show', $bill)
            ->with('success', 'Tagihan berhasil diperbarui.');
    }

    public function destroy(int $bill): RedirectResponse
    {
        DB::transaction(function () use ($bill): void {
            $billRecord = DB::table('bills')
                ->where('id', $bill)
                ->lockForUpdate()
                ->first();

            abort_if($billRecord === null, 404);

            if ((int) $billRecord->amount_paid > 0 || DB::table('bill_payments')->where('bill_id', $bill)->exists()) {
                throw ValidationException::withMessages([
                    'bill' => 'Tagihan yang sudah memiliki pembayaran tidak dapat dihapus.',
                ]);
            }

            DB::table('bills')->where('id', $bill)->delete();
        });

        return redirect()
            ->route('staff.billing.index')
            ->with('success', 'Tagihan berhasil dihapus.');
    }

    public function recordPayment(Request $request, int $bill): RedirectResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'payment_method' => ['required', Rule::in(self::PAYMENT_METHODS)],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        DB::transaction(function () use ($request, $bill, $validated): void {
            $billRecord = DB::table('bills')
                ->where('id', $bill)
                ->lockForUpdate()
                ->first();

            abort_if($billRecord === null, 404);

            if ($billRecord->status === 'Cancelled') {
                throw ValidationException::withMessages([
                    'amount' => 'Pembayaran tidak dapat dicatat untuk tagihan yang dibatalkan.',
                ]);
            }

            $amount = (int) $validated['amount'];
            $balanceDue = (int) $billRecord->total_amount - (int) $billRecord->amount_paid;

            if ($amount > $balanceDue) {
                throw ValidationException::withMessages([
                    'amount' => 'Jumlah pembayaran melebihi sisa tagihan.',
                ]);
            }

            $now = now();
            $amountPaid = (int) $billRecord->amount_paid + $amount;

            DB::table('bill_payments')->insert([
                'bill_id' => $bill,
                'recorded_by_user_id' => $request->user()->id,
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'reference' => $validated['reference'] ?? null,
                'paid_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('bills')
                ->where('id', $bill)
                ->update([
                    'amount_paid' => $amountPaid,
                    'status' => $amountPaid === (int) $billRecord->total_amount ? 'Paid' : 'Partially Paid',
                    'updated_at' => $now,
                ]);
        });

        return redirect()
            ->route('staff.billing.show', $bill)
            ->with('success', 'Pembayaran berhasil dicatat.');
    }

    public function cancel(int $bill): RedirectResponse
    {
        DB::transaction(function () use ($bill): void {
            $billRecord = DB::table('bills')
                ->where('id', $bill)
                ->lockForUpdate()
                ->first();

            abort_if($billRecord === null, 404);

            if ($billRecord->status !== 'Unpaid' || (int) $billRecord->amount_paid > 0) {
                throw ValidationException::withMessages([
                    'bill' => 'Hanya tagihan yang belum dibayar yang dapat dibatalkan.',
                ]);
            }

            DB::table('bills')
                ->where('id', $bill)
                ->update([
                    'status' => 'Cancelled',
                    'updated_at' => now(),
                ]);
        });

        return redirect()
            ->route('staff.billing.show', $bill)
            ->with('success', 'Tagihan berhasil dibatalkan.');
    }

    /**
     * @return array<string, mixed>
     */
    private function billRules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patient_profiles,id'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.item_name' => ['required', 'string', 'not_regex:/^\s*$/u', 'max:150'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'integer', 'min:1', 'max:999999999999'],
        ];
    }

    /**
     * @param  array<int, array{item_name: string, quantity: int|string, unit_price: int|string}>  $items
     * @return list<array{item_name: string, quantity: int, unit_price: int}>
     */
    private function normalizeItems(array $items): array
    {
        return array_map(static fn (array $item): array => [
            'item_name' => trim($item['item_name']),
            'quantity' => (int) $item['quantity'],
            'unit_price' => (int) $item['unit_price'],
        ], $items);
    }

    /**
     * @param  list<array{item_name: string, quantity: int, unit_price: int}>  $items
     */
    private function totalAmount(array $items): int
    {
        return array_sum(array_map(
            static fn (array $item): int => $item['quantity'] * $item['unit_price'],
            $items,
        ));
    }

    /**
     * @param  list<array{item_name: string, quantity: int, unit_price: int}>  $items
     */
    private function replaceItems(int $billId, array $items, Carbon $now): void
    {
        DB::table('bill_items')->insert(array_map(
            static fn (array $item): array => [
                'bill_id' => $billId,
                'item_name' => $item['item_name'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $items,
        ));
    }

    private function loadBill(int $billId): object
    {
        $bill = DB::table('bills')
            ->join('patient_profiles as patients', 'patients.id', '=', 'bills.patient_id')
            ->where('bills.id', $billId)
            ->select([
                'bills.id',
                'bills.bill_number',
                'bills.patient_id',
                'bills.total_amount',
                'bills.amount_paid',
                'bills.status',
                'bills.created_at',
                'patients.full_name as patient_name',
                'patients.medical_record_number',
            ])
            ->first();

        abort_if($bill === null, 404);

        $bill->items = DB::table('bill_items')
            ->where('bill_id', $billId)
            ->orderBy('id')
            ->get();
        $bill->payments = DB::table('bill_payments')
            ->join('users', 'users.id', '=', 'bill_payments.recorded_by_user_id')
            ->where('bill_payments.bill_id', $billId)
            ->select([
                'bill_payments.amount',
                'bill_payments.payment_method',
                'bill_payments.reference',
                'bill_payments.paid_at',
                'users.email as recorded_by',
            ])
            ->orderBy('bill_payments.id')
            ->get();
        $bill->balance_due = $bill->status === 'Cancelled'
            ? 0
            : (int) $bill->total_amount - (int) $bill->amount_paid;

        return $bill;
    }

    private function ensureEditable(object $bill): void
    {
        if (in_array($bill->status, ['Paid', 'Cancelled'], true)) {
            throw ValidationException::withMessages([
                'bill' => 'Tagihan lunas atau dibatalkan tidak dapat diedit.',
            ]);
        }
    }

    private function staffRoleLabel(Request $request): string
    {
        return (string) DB::table('roles')
            ->where('id', $request->user()->role_id)
            ->value('role_name');
    }
}
