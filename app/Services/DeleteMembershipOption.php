<?php

namespace App\Services;

use App\Models\MembershipCategory;
use App\Models\MembershipLevel;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

class DeleteMembershipOption
{
    public function handle(
        MembershipCategory|MembershipLevel $option
    ): void {
        if ($option->members()->exists()) {
            $this->rejectDeletion();
        }

        try {
            $option->delete();
        } catch (QueryException $exception) {
            $isForeignKeyFailure =
                (int) ($exception->errorInfo[1] ?? 0) === 1451
                || str_contains(
                    strtolower($exception->getMessage()),
                    'foreign key constraint'
                );

            if ($isForeignKeyFailure) {
                $this->rejectDeletion();
            }

            throw $exception;
        }
    }

    private function rejectDeletion(): never
    {
        throw ValidationException::withMessages([
            'catalog' => 'This item is linked to existing records. '
                .'Deactivate it instead.',
        ]);
    }
}