<?php
namespace SaaS\Repositories;

class CustomerRepository extends BaseRepository {
    protected string $table = 'tbl_customer';
    protected string $primaryKey = 'id';
}
