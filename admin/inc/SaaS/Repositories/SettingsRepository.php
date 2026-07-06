<?php
namespace SaaS\Repositories;

class SettingsRepository extends BaseRepository {
    protected string $table = 'tbl_settings';
    protected string $primaryKey = 'id';

    public function getSettings() {
        $settings = $this->findAll([], '', 1);
        return $settings ? $settings[0] : null;
    }
}
