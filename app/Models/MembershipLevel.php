<?php

namespace App\Models;

use App\Concerns\SavesWithAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipLevel extends Model
{
    use SavesWithAudit;

    protected $fillable = [
        'name',
        'fee',
        'validity_days',
        'benefits',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'validity_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function terms(): array
    {
        return [
            'fee' => $this->fee,
            'validity_period' => $this->validity_days.' days',
            'benefits' => $this->benefits,
        ];
    }
}