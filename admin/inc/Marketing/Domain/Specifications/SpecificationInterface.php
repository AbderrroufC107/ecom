<?php
namespace Marketing\Domain\Specifications;

interface SpecificationInterface
{
    /**
     * Check if the given entity satisfies the specification.
     */
    public function isSatisfiedBy($entity): bool;
}
