<?php
namespace Marketing\Domain\ValueObjects;

use InvalidArgumentException;

class CampaignBudget
{
    private int $amountInCents;
    private string $currency;

    public function __construct(int $amountInCents, string $currency = 'DZD')
    {
        if ($amountInCents < 100) {
            throw new InvalidArgumentException("Budget must be at least 100 cents (1 DZD).");
        }
        
        $this->amountInCents = $amountInCents;
        $this->currency = strtoupper($currency);
    }

    public function getAmount(): int
    {
        return $this->amountInCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getFormatted(): string
    {
        return number_format($this->amountInCents / 100, 2) . ' ' . $this->currency;
    }
}
