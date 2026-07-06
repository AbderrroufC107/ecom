<?php
namespace SaaS\Repositories;

class EmployeeRepository extends BaseRepository {
    protected string $table = 'tbl_employee';
    protected string $primaryKey = 'id';
}
