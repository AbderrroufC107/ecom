<?php

$repositories = [
    'CustomerRepository' => ['table' => 'tbl_customer', 'primaryKey' => 'id'],
    'OrderRepository' => ['table' => 'tbl_order', 'primaryKey' => 'id'],
    'ProductRepository' => ['table' => 'tbl_product', 'primaryKey' => 'p_id'],
    'SettingsRepository' => ['table' => 'tbl_settings', 'primaryKey' => 'id'],
    'ConversationRepository' => ['table' => 'tbl_omni_conversations', 'primaryKey' => 'id'],
    'ChannelRepository' => ['table' => 'tbl_omni_channels', 'primaryKey' => 'id'],
    'AiTaskRepository' => ['table' => 'tbl_ai_tasks', 'primaryKey' => 'id'],
    'KnowledgeRepository' => ['table' => 'tbl_ai_knowledge', 'primaryKey' => 'id'],
    'EventStoreRepository' => ['table' => 'tbl_omni_events', 'primaryKey' => 'id'],
    // Adding EmployeeRepository just in case since they log into the tenant
    'EmployeeRepository' => ['table' => 'tbl_employee', 'primaryKey' => 'id']
];

$dir = 'C:/xampp/htdocs/ecom/admin/inc/SaaS/Repositories';
if (!is_dir($dir)) mkdir($dir, 0777, true);

foreach ($repositories as $class => $meta) {
    $table = $meta['table'];
    $pk = $meta['primaryKey'];
    $content = <<<PHP
<?php
namespace SaaS\Repositories;

class $class extends BaseRepository {
    protected string \$table = '$table';
    protected string \$primaryKey = '$pk';
}

PHP;
    file_put_contents("$dir/$class.php", $content);
    echo "Created $class.php\n";
}

// Special case: Settings Repository might need to fetch the ONLY record for the tenant
$settingsContent = <<<PHP
<?php
namespace SaaS\Repositories;

class SettingsRepository extends BaseRepository {
    protected string \$table = 'tbl_settings';
    protected string \$primaryKey = 'id';

    public function getSettings() {
        \$settings = \$this->findAll([], '', 1);
        return \$settings ? \$settings[0] : null;
    }
}

PHP;
file_put_contents("$dir/SettingsRepository.php", $settingsContent);
echo "Updated SettingsRepository.php\n";
