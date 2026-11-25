<?php

namespace App\Concerns;

trait SavesWithAudit
{
    public function save(array $options = []): bool
    {
        return $this->getConnection()->transaction(
            fn (): bool => parent::save($options)
        );
    }

    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(
            fn (): ?bool => parent::delete()
        );
    }
}