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
