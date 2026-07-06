<?php
namespace SaaS\Repositories;

class OrderRepository extends BaseRepository {
    protected string $table = 'tbl_order';
    protected string $primaryKey = 'id';
}
