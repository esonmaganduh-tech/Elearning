<?php

namespace App\Support;

use InvalidArgumentException;

class Money
{
    public static function cents(string $amount): int
    {
        $amount = trim($amount);

        if (str_starts_with($amount, '+')) {
            $amount = substr($amount, 1);
        }

        if (
            ! preg_match(
                '/\A([0-9]{0,10})(?:\.([0-9]{0,2}))?\z/',
                $amount,
                $parts
            )
        ) {
            throw new InvalidArgumentException(
                'Invalid monetary amount.'
            );
        }

        $whole = $parts[1];
        $fraction = $parts[2] ?? '';

        if ($whole === '' && $fraction === '') {
            throw new InvalidArgumentException(
                'Invalid monetary amount.'
            );
        }

        return ((int) $whole * 100)
            + (int) str_pad($fraction, 2, '0', STR_PAD_RIGHT);
    }

    public static function decimal(int $cents): string
    {
        if ($cents < 0) {
            throw new InvalidArgumentException(
                'A monetary amount cannot be negative.'
            );
        }

        return intdiv($cents, 100)
            .'.'
            .str_pad(
                (string) ($cents % 100),
                2,
                '0',
                STR_PAD_LEFT
            );
    }
}