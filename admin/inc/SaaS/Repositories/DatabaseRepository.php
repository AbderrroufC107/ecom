<?php
namespace SaaS\Repositories;

/**
 * Fallback repository for complex joins or queries that don't fit a specific table.
 */
class DatabaseRepository extends BaseRepository {
    protected string $table = 'tbl_tenants'; // Dummy table for fallback
    protected string $primaryKey = 'id';
}
