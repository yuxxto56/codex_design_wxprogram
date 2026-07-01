<?php
declare(strict_types=1);

require __DIR__ . '/database_common.php';

$root = dirname(__DIR__);

try {
    $config = db_config(load_env_file($root . '/.env'));
    $status = base_status($config);

    $serverPdo = connect_mysql($config, false);
    $status['connected'] = true;

    $schemaPath = $root . '/database/schema.sql';
    $seedPath = $root . '/database/seed.sql';
    if (!is_file($schemaPath) || !is_file($seedPath)) {
        throw new RuntimeException('schema.sql 或 seed.sql 不存在');
    }

    $serverPdo->exec(file_get_contents($schemaPath));

    $dbPdo = connect_mysql($config, true);
    $status['database_exists'] = true;
    ensure_category_user_columns($dbPdo);
    $dbPdo->exec(file_get_contents($seedPath));

    $inspection = inspect_database($dbPdo);
    $status = array_merge($status, $inspection);

    if (!$status['tables_ready'] && ($status['missing_tables'] ?? []) !== []) {
        $status['error_type'] = '表结构不完整';
        $status['error_message'] = '缺少表: ' . implode(', ', $status['missing_tables']);
    } elseif (!$status['tables_ready']) {
        $status['error_type'] = '表结构不完整';
        $status['error_message'] = 'categories 缺少 user_id 字段或 idx_user_type_status_sort 索引';
    } elseif (!$status['seed_ready']) {
        $status['error_type'] = '初始化数据不完整';
        $status['error_message'] = "categories 期望 8 条，实际 {$status['category_count']} 条";
    }

    print_json_and_exit($status);
} catch (Throwable $exception) {
    $status = isset($config) ? base_status($config) : base_status([
        'host' => '',
        'port' => 0,
        'database' => '',
        'user' => '',
    ]);
    $status['error_type'] = classify_pdo_error($exception);
    $status['error_message'] = $exception->getMessage();
    print_json_and_exit($status);
}

function ensure_category_user_columns(PDO $pdo): void
{
    $columns = $pdo->query("SHOW COLUMNS FROM `categories` LIKE 'user_id'")->fetchAll();
    if ($columns === []) {
        $pdo->exec("ALTER TABLE `categories` ADD COLUMN `user_id` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属用户ID，0表示系统分类' AFTER `id`");
    }

    $indexes = $pdo->query("SHOW INDEX FROM `categories` WHERE Key_name = 'idx_user_type_status_sort'")->fetchAll();
    if ($indexes === []) {
        $pdo->exec('ALTER TABLE `categories` ADD KEY `idx_user_type_status_sort` (`user_id`, `type`, `status`, `sort`)');
    }
}
