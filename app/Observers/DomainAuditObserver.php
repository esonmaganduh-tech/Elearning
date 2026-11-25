<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Member;
use App\Models\MembershipApplication;
use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use App\Models\MembershipTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class DomainAuditObserver
{
    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }

    private function record(Model $model, string $action): void
    {
        // Existing isolated migration tests may intentionally use an
        // earlier schema that predates the audit table.
        if (
            app()->runningUnitTests()
            && ! $model->getConnection()
                ->getSchemaBuilder()
                ->hasTable('audit_logs')
        ) {
            return;
        }

        $attributes = $model->getAttributes();

        $changes = $action === 'updated'
            ? $model->getChanges()
            : $attributes;

        $changes = Arr::except($changes, [
            'created_at',
            'updated_at',
            'submission_key',
            'idempotency_key',
        ]);

        if ($changes === []) {
            return;
        }

        $fields = array_keys($changes);
        $allowed = $this->snapshotFields($model);

        $before = match ($action) {
            'created' => [],
            'updated' => Arr::only(
                $model->getRawOriginal(),
                array_intersect($fields, $allowed)
            ),
            'deleted' => Arr::only($attributes, $allowed),
        };

        $after = match ($action) {
            'created' => Arr::only($attributes, $allowed),
            'updated' => Arr::only(
                $changes,
                array_intersect($fields, $allowed)
            ),
            'deleted' => [],
        };

        $actor = $this->actor($model, $action);

        $publicApplication = $model instanceof MembershipApplication
            && $action === 'created'
            && request()->routeIs('membership.store');

        $kind = $actor
            ? ($actor->is_admin ? 'staff' : 'user')
            : ($publicApplication ? 'applicant' : 'system');

        AuditLog::on($model->getConnectionName())->create([
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name
                ?? ($publicApplication ? 'Public applicant' : 'System'),
            'actor_kind' => $kind,
            'subject_type' => class_basename($model),
            'subject_id' => $model->getKey(),
            'reference_no' => $attributes['reference_no']
                ?? $attributes['membership_no']
                ?? null,
            'action' => $action,
            'changed_fields' => $fields,
            'old_values' => $before,
            'new_values' => $after,
            'created_at' => now(),
        ]);
    }

    private function snapshotFields(Model $model): array
    {
        return match (true) {
            $model instanceof Member => [
                'membership_no',
                'membership_category_id',
                'membership_level_id',
                'fee',
                'validity_period',
                'status',
                'joined_at',
                'membership_started_on',
                'membership_expires_on',
            ],

            $model instanceof MembershipCategory => [
                'name',
                'level',
                'fee',
                'is_active',
            ],

            $model instanceof MembershipLevel => [
                'name',
                'fee',
                'validity_days',
                'is_active',
            ],

            $model instanceof MembershipTransaction => [
                'reference_no',
                'member_id',
                'membership_category_id',
                'membership_level_id',
                'type',
                'status',
                'category_fee',
                'level_fee',
                'total_amount',
                'validity_days',
                'starts_on',
                'expires_on',
                'created_by',
                'approved_by',
                'approved_at',
                'cancelled_by',
                'cancelled_at',
            ],

            $model instanceof Payment => [
                'reference_no',
                'membership_transaction_id',
                'amount',
                'method',
                'received_by',
                'paid_at',
            ],

            $model instanceof MembershipApplication => [
                'reference_no',
                'membership_category_id',
                'status',
                'member_id',
                'registered_by',
                'registered_at',
                'verification_sent_at',
                'privacy_consented_at',
            ],

            default => [],
        };
    }

    private function actor(Model $model, string $action): ?User
    {
        $id = Auth::id();

        if ($model instanceof Payment) {
            $id = $model->received_by;
        }

        if ($model instanceof MembershipTransaction) {
            $id = match (true) {
                $action === 'created' => $model->created_by,

                $model->wasChanged('status')
                    && $model->status === 'approved'
                        => $model->approved_by,

                $model->wasChanged('status')
                    && $model->status === 'cancelled'
                        => $model->cancelled_by,

                $model->wasChanged('status')
                    && $model->status === 'paid'
                        => $model->payments()
                            ->latest('id')
                            ->value('received_by') ?? $id,

                default => $id,
            };
        }

        if ($model instanceof MembershipApplication) {
            if ($model->wasChanged('registered_by')) {
                $id = $model->registered_by;
            }

            if (! $id && $model->status === 'approved' && $model->member_id) {
                $id = MembershipTransaction::query()
                    ->where('member_id', $model->member_id)
                    ->where('type', 'membership')
                    ->where('status', 'approved')
                    ->latest('id')
                    ->value('approved_by');
            }
        }

        return $id ? User::find($id) : null;
    }
}