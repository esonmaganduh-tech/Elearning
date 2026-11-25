<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    public const SUBJECTS = [
        'Member' => 'Member',
        'MembershipCategory' => 'Category',
        'MembershipLevel' => 'Level',
        'MembershipApplication' => 'Application',
        'MembershipTransaction' => 'Transaction',
        'Payment' => 'Payment',
    ];

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_kind',
        'subject_type',
        'subject_id',
        'reference_no',
        'action',
        'changed_fields',
        'old_values',
        'new_values',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'subject_id' => 'integer',
            'changed_fields' => 'array',
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit entries cannot be edited.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit entries cannot be deleted.');
        });
    }
}