<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveMemberRequest;
use App\Models\Member;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'category' => [
                'nullable',
                'integer',
                'exists:membership_categories,id',
            ],
            'level' => [
                'nullable',
                'integer',
                'exists:membership_levels,id',
            ],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $category = $filters['category'] ?? null;
        $level = $filters['level'] ?? null;

        $query = Member::query()->with([
            'membershipCategory',
            'membershipLevel',
        ]);

        foreach (
            preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY)
            as $term
        ) {
            $query->where(function ($query) use ($term) {
                $query->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('membership_no', 'like', "%{$term}%");
            });
        }

        $members = $query
            ->when(
                $category,
                fn ($query) => $query->where(
                    'membership_category_id',
                    $category
                )
            )
            ->when(
                $level,
                fn ($query) => $query->where(
                    'membership_level_id',
                    $level
                )
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('members.index', [
            'members' => $members,
            'categories' => MembershipCategory::orderBy('name')->get(),
            'levels' => MembershipLevel::orderBy('name')->get(),
            'search' => $search,
            'category' => $category,
            'level' => $level,
        ]);
    }

    public function create()
    {
        return view('members.create', $this->formData());
    }

    public function store(SaveMemberRequest $request)
    {
        $data = $this->memberData($request);
        $data['status'] = 'Inactive';

        $member = Member::create($data);

        return redirect()
            ->route('members.show', $member)
            ->with(
                'success',
                'Member registered. Create a membership transaction to continue.'
            );
    }

    public function show(Member $member)
    {
        $member->load([
            'membershipCategory',
            'membershipLevel',
        ]);

        $member->loadCount('transactions');

        $hasInitial = $member->transactions()
            ->where('type', 'membership')
            ->whereIn('status', ['approved', 'expired'])
            ->exists();

        $openTransaction = $member->transactions()
            ->whereIn('status', ['pending', 'paid'])
            ->latest('id')
            ->first();

        $member->load('application');

        return view(
            'members.show',
            compact('member', 'hasInitial', 'openTransaction')
        );
    }

    public function edit(Member $member)
    {
        return view('members.edit', $this->formData($member));
    }

    public function update(SaveMemberRequest $request, Member $member)
    {
        DB::transaction(function () use ($request, $member) {
            $locked = Member::query()
                ->lockForUpdate()
                ->findOrFail($member->id);

            $data = $request->validated();

            $planLocked = $locked->transactions()
                ->where('status', '!=', 'cancelled')
                ->exists();

            if (
                $planLocked
                && (
                    (int) $data['membership_category_id']
                        !== $locked->membership_category_id
                    || (int) $data['membership_level_id']
                        !== $locked->membership_level_id
                )
            ) {
                throw ValidationException::withMessages([
                    'membership_level_id' =>
                        'Use a renewal to change this membership plan.',
                ]);
            }

            $locked->update($this->memberData($request, $locked));
        }, attempts: 3);

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Member details updated.');
    }

    public function destroy(Member $member)
    {
        try {
            DB::transaction(function () use ($member) {
                $locked = Member::query()
                    ->lockForUpdate()
                    ->findOrFail($member->id);

                if ($locked->transactions()->exists()) {
                    throw ValidationException::withMessages([
                        'member' => 'This member has transaction history. '
                            .'Deactivate the member instead.',
                    ]);
                }

                $locked->delete();
            }, attempts: 3);
        } catch (QueryException $exception) {
            if (
                (int) ($exception->errorInfo[1] ?? 0) === 1451
                || str_contains(
                    strtolower($exception->getMessage()),
                    'foreign key constraint'
                )
            ) {
                throw ValidationException::withMessages([
                    'member' => 'Related records prevent deletion. '
                        .'Deactivate the member instead.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('members.index')
            ->with('success', 'Member permanently deleted.');
    }

    public function deactivate(Member $member)
    {
        $member->update(['status' => 'Inactive']);

        return redirect()
            ->route('members.show', $member)
            ->with('success', 'Member deactivated.');
    }

    private function memberData(
        SaveMemberRequest $request,
        ?Member $member = null
    ): array {
        $data = $request->validated();

        if (
            ! $member
            || (int) $data['membership_level_id']
                !== $member->membership_level_id
        ) {
            $level = MembershipLevel::findOrFail(
                $data['membership_level_id']
            );

            $data = array_merge($data, $level->terms());
        }

        return $data;
    }

    private function formData(?Member $member = null): array
    {
        $categories = MembershipCategory::query()
            ->where(function ($query) use ($member) {
                $query->where('is_active', true);

                if ($member) {
                    $query->orWhere(
                        'id',
                        $member->membership_category_id
                    );
                }
            })
            ->orderBy('name')
            ->get();

        $levels = MembershipLevel::query()
            ->where(function ($query) use ($member) {
                $query->where('is_active', true);

                if ($member) {
                    $query->orWhere(
                        'id',
                        $member->membership_level_id
                    );
                }
            })
            ->orderBy('fee')
            ->get();

        $planLocked = $member
            && $member->transactions()
                ->where('status', '!=', 'cancelled')
                ->exists();

        return compact('member', 'categories', 'levels', 'planLocked');
    }
}