<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Services\MembershipBilling;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'member' => ['nullable', 'integer', 'exists:members,id'],
            'status' => [
                'nullable',
                Rule::in([
                    'pending', 'paid', 'approved', 'expired', 'cancelled',
                ]),
            ],
        ]);

        $selectedMember = isset($filters['member'])
            ? Member::findOrFail($filters['member'])
            : null;

        $status = $filters['status'] ?? '';

        $transactions = MembershipTransaction::with('payments')
            ->when(
                $selectedMember,
                fn ($query) => $query->where(
                    'member_id',
                    $selectedMember->id
                )
            )
            ->when(
                $status !== '',
                fn ($query) => $query->where('status', $status)
            )
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view(
            'transactions.index',
            compact('transactions', 'selectedMember', 'status')
        );
    }

    public function create(Request $request, Member $member)
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(['membership', 'renewal'])],
        ]);

        $type = $data['type'] ?? 'membership';

        $open = $member->transactions()
            ->whereIn('status', ['pending', 'paid'])
            ->latest('id')
            ->first();

        if ($open) {
            return redirect()->route('transactions.show', $open);
        }

        $member->load(['membershipCategory', 'membershipLevel']);

        $covered = $member->membership_expires_on
            && $member->membership_expires_on->toDateString()
                >= today('Asia/Manila')->toDateString();

        $planLocked = $type === 'membership' || $covered;

        $categories = $planLocked
            ? collect([$member->membershipCategory])
            : MembershipCategory::where('is_active', true)
                ->orderBy('name')->get();

        $levels = $planLocked
            ? collect([$member->membershipLevel])
            : MembershipLevel::where('is_active', true)
                ->orderBy('fee')->get();

        $available = $planLocked
            ? $member->membershipCategory->is_active
                && $member->membershipLevel->is_active
            : $categories->isNotEmpty() && $levels->isNotEmpty();

        $initialTotal = Money::decimal(
            Money::cents($member->membershipCategory->fee)
            + Money::cents($member->membershipLevel->fee)
        );

        return view('transactions.create', compact(
            'member',
            'type',
            'planLocked',
            'categories',
            'levels',
            'available',
            'initialTotal'
        ));
    }

    public function store(
        Request $request,
        Member $member,
        MembershipBilling $billing
    ) {
        $data = $request->validate([
            'type' => ['required', Rule::in(['membership', 'renewal'])],
            'membership_category_id' => [
                'required_if:type,renewal',
                'nullable',
                'integer',
                'exists:membership_categories,id',
            ],
            'membership_level_id' => [
                'required_if:type,renewal',
                'nullable',
                'integer',
                'exists:membership_levels,id',
            ],
        ]);

        $transaction = $data['type'] === 'renewal'
            ? $billing->createRenewal(
                $member->id,
                (int) $data['membership_category_id'],
                (int) $data['membership_level_id'],
                $request->user()
            )
            : $billing->createInitial($member->id, $request->user());

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', 'Transaction ready for review.');
    }

    public function show(MembershipTransaction $transaction)
    {
        return view('transactions.show', $this->details($transaction));
    }

    public function approve(
        Request $request,
        MembershipTransaction $transaction,
        MembershipBilling $billing
    ) {
        $billing->approve($transaction->id, $request->user());

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', 'Membership transaction approved.');
    }

    public function cancel(
        Request $request,
        MembershipTransaction $transaction,
        MembershipBilling $billing
    ) {
        $billing->cancel($transaction->id, $request->user());

        return redirect()
            ->route('transactions.show', $transaction)
            ->with('success', 'Transaction cancelled.');
    }

    public function receipt(MembershipTransaction $transaction)
    {
        $data = $this->details($transaction);

        if (! $data['canReceipt']) {
            return redirect()
                ->route('transactions.show', $transaction)
                ->withErrors([
                    'receipt' => 'Record a payment before printing a receipt.',
                ]);
        }

        return view('transactions.receipt', $data);
    }

    private function details(MembershipTransaction $transaction): array
    {
        $transaction->load([
            'member',
            'payments.receiver',
            'creator',
            'approver',
        ]);

        $paidCents = $transaction->paidCents();
        $totalCents = Money::cents($transaction->total_amount);
        $balanceCents = max($totalCents - $paidCents, 0);

        $canReceipt = $paidCents > 0
            || (
                $totalCents === 0
                && in_array(
                    $transaction->status,
                    ['paid', 'approved', 'expired'],
                    true
                )
            );

        return [
            'transaction' => $transaction,
            'paidCents' => $paidCents,
            'totalCents' => $totalCents,
            'balanceCents' => $balanceCents,
            'canReceipt' => $canReceipt,
            'paymentKey' => (string) Str::uuid(),
        ];
    }
}