<?php
namespace SaaS\Repositories;

class EventStoreRepository extends BaseRepository {
    protected string $table = 'tbl_omni_events';
    protected string $primaryKey = 'id';
}
