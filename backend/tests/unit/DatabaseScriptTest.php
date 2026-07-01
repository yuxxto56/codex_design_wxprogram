<?php
declare(strict_types=1);

$runner->test('database scripts exist and expose explicit status fields', function () use ($runner): void {
    $root = dirname(__DIR__, 2);
    $initPath = $root . '/scripts/init_database.php';
    $checkPath = $root . '/scripts/check_database.php';

    $runner->assertTrue(is_file($initPath), 'init_database.php should exist');
    $runner->assertTrue(is_file($checkPath), 'check_database.php should exist');

    $initContent = file_get_contents($initPath);
    $checkContent = file_get_contents($checkPath);

    foreach (['connected', 'database_exists', 'tables_ready', 'seed_ready'] as $field) {
        $runner->assertTrue(strpos($initContent, $field) !== false, "init script should output {$field}");
        $runner->assertTrue(strpos($checkContent, $field) !== false, "check script should output {$field}");
    }
});

$runner->test('database error classifier explains refused connection in Chinese environments', function () use ($runner): void {
    require_once dirname(__DIR__, 2) . '/scripts/database_common.php';

    $error = new RuntimeException('SQLSTATE[HY000] [2002] 由于目标计算机积极拒绝，无法连接。');

    $runner->assertSame('端口未开放或服务拒绝连接', classify_pdo_error($error));
});

$runner->test('database scripts include user category migration fields', function () use ($runner): void {
    $root = dirname(__DIR__, 2);
    $schemaContent = file_get_contents($root . '/database/schema.sql') ?: '';
    $initContent = file_get_contents($root . '/scripts/init_database.php') ?: '';

    $runner->assertTrue(strpos($schemaContent, '`user_id` INT(10) UNSIGNED NOT NULL DEFAULT 0') !== false, 'categories schema should include user_id');
    $runner->assertTrue(strpos($schemaContent, 'idx_user_type_status_sort') !== false, 'categories schema should include user/type/status/sort index');
    $runner->assertTrue(strpos($initContent, 'ensure_category_user_columns') !== false, 'init script should run idempotent category user migration');
    $runner->assertTrue(strpos($initContent, 'idx_user_type_status_sort') !== false, 'init script should ensure custom category index');
});
