<?php
declare(strict_types=1);

require __DIR__ . '/database_common.php';

$root = dirname(__DIR__);

try {
    $config = db_config(load_env_file($root . '/.env'));
    $status = base_status($config);

    $serverPdo = connect_mysql($config, false);
    $status['connected'] = true;

    $stmt = $serverPdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
    $stmt->execute([$config['database']]);
    $status['database_exists'] = (bool)$stmt->fetchColumn();
    if (!$status['database_exists']) {
        $status['error_type'] = '目标库不存在';
        $status['error_message'] = "数据库 {$config['database']} 不存在";
        print_json_and_exit($status);
    }

    $dbPdo = connect_mysql($config, true);
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
