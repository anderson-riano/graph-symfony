<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObject;

use App\Domain\Shared\Exception\DomainException;

final class Money
{
    private function __construct()
    {
    }

    public static function normalize(string $amount): string
    {
        $cents = self::toCents($amount);

        return self::fromCents($cents);
    }

    public static function add(string $left, string $right): string
    {
        return self::fromCents(self::toCents($left) + self::toCents($right));
    }

    public static function multiply(string $amount, int $quantity): string
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be greater than zero.');
        }

        return self::fromCents(self::toCents($amount) * $quantity);
    }

    public static function isGreaterThanZero(string $amount): bool
    {
        return self::toCents($amount) > 0;
    }

    public static function toCents(string $amount): int
    {
        $amount = trim($amount);
        if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new DomainException(sprintf('Invalid money amount "%s".', $amount));
        }

        $negative = str_starts_with($amount, '-');
        if ($negative) {
            $amount = substr($amount, 1);
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $fraction = str_pad($fraction, 2, '0');
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    public static function fromCents(int $cents): string
    {
        $negative = $cents < 0;
        $absolute = abs($cents);
        $whole = intdiv($absolute, 100);
        $fraction = $absolute % 100;
        $amount = sprintf('%d.%02d', $whole, $fraction);

        return $negative ? sprintf('-%s', $amount) : $amount;
    }
}
