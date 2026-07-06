<?php
namespace Marketing\Application\UnitOfWork;

use Marketing\Domain\Events\DomainEventInterface;

interface UnitOfWorkInterface
{
    /**
     * Start a new transaction.
     */
    public function begin(): void;
    
    /**
     * Commit the transaction and publish any recorded events.
     */
    public function commit(): void;
    
    /**
     * Rollback the transaction and discard recorded events.
     */
    public function rollback(): void;
    
    /**
     * Register a domain event to be published upon commit.
     */
    public function registerEvent(DomainEventInterface $event): void;
}
