<?php

namespace App\Models;

use App\Concerns\SavesWithAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipCategory extends Model
{
    use SavesWithAudit;

    protected $fillable = [
        'name',
        'level',
        'fee',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(MembershipApplication::class);
    }
}