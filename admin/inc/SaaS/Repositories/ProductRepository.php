<?php
namespace SaaS\Repositories;

class ProductRepository extends BaseRepository {
    protected string $table = 'tbl_product';
    protected string $primaryKey = 'p_id';
}
