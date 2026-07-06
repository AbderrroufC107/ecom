<?php
namespace Marketing\Domain\ValueObjects;

use InvalidArgumentException;

class ROAS
{
    private float $value;

    public function __construct(float $revenue, float $spend)
    {
        if ($spend < 0 || $revenue < 0) {
            throw new InvalidArgumentException("Spend and Revenue cannot be negative.");
        }
        
        $this->value = $spend > 0 ? ($revenue / $spend) : 0.0;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function isProfitable(): bool
    {
        return $this->value > 1.0;
    }
}
