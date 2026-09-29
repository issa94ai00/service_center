<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Money value object to keep money arithmetic free of float drift.
 * All operations work on integer minor units (scale 2).
 */
final class Money
{
    private function __construct(private readonly int $cents) {}

    public static function fromDecimal(int|float|string|null $amount): self
    {
        if ($amount === null || $amount === '') {
            return new self(0);
        }

        if (! is_numeric($amount)) {
            throw new InvalidArgumentException("Invalid money amount: {$amount}");
        }

        $cents = (int) round((float) $amount * 100);

        return new self($cents);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function fromCents(int $cents): self
    {
        return new self($cents);
    }

    public function add(self $other): self
    {
        return new self($this->cents + $other->cents);
    }

    public function subtract(self $other): self
    {
        return new self($this->cents - $other->cents);
    }

    public function multiplyBy(int|float|string $factor): self
    {
        $factor = (float) $factor;

        return new self((int) round($this->cents * $factor));
    }

    public function percentage(int|float|string $percent): self
    {
        return new self((int) round($this->cents * (float) $percent / 100));
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function absolute(): self
    {
        return new self(abs($this->cents));
    }

    public function toDecimal(): string
    {
        return number_format($this->cents / 100, 2, '.', '');
    }

    public function toCents(): int
    {
        return $this->cents;
    }
}
