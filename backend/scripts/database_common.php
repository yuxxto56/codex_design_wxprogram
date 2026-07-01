<?php
declare(strict_types=1);

/**
 * 数据库初始化和健康检查脚本的公共函数。
 *
 * 这些脚本独立于 ThinkPHP 运行，直接读取 backend/.env 并通过 PDO 连接 MySQL。
 */

function load_env_file(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException("配置文件不存在: {$path}");
    }

    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }

    return $env;
}

function db_config(array $env): array
{
    return [
        'host' => $env['DATABASE_HOST'] ?? $env['MYSQL_HOST'] ?? '127.0.0.1',
        'port' => $env['DATABASE_PORT'] ?? $env['MYSQL_PORT'] ?? '3306',
        'database' => $env['DATABASE_NAME'] ?? $env['MYSQL_DATABASE'] ?? 'mengmeng_ledger',
        'user' => $env['DATABASE_USER'] ?? $env['MYSQL_USERNAME'] ?? 'root',
        'password' => $env['DATABASE_PASS'] ?? $env['MYSQL_PASSWORD'] ?? '',
        'charset' => $env['DATABASE_CHARSET'] ?? $env['MYSQL_CHARSET'] ?? 'utf8mb4',
    ];
}

function pdo_options(): array
{
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 8,
    ];
}

function connect_mysql(array $config, bool $withDatabase): PDO
{
    $database = $withDatabase ? ';dbname=' . $config['database'] : '';
    $dsn = "mysql:host={$config['host']};port={$config['port']}{$database};charset={$config['charset']}";

    return new PDO($dsn, $config['user'], $config['password'], pdo_options());
}

function classify_pdo_error(Throwable $exception): string
{
    $message = $exception->getMessage();
    if (stripos($message, 'Access denied') !== false || strpos($message, '1045') !== false) {
        return '用户名或密码错误';
    }
    if (stripos($message, 'Unknown database') !== false || strpos($message, '1049') !== false) {
        return '目标库不存在';
    }
    if (stripos($message, 'Connection timed out') !== false || stripos($message, 'No route to host') !== false) {
        return '网络不可达或连接超时';
    }
    if (stripos($message, 'Connection refused') !== false
        || stripos($message, 'actively refused') !== false
        || strpos($message, '积极拒绝') !== false
        || strpos($message, '无法连接') !== false) {
        return '端口未开放或服务拒绝连接';
    }
    if (stripos($message, 'permission') !== false || strpos($message, '1044') !== false) {
        return '数据库权限不足';
    }

    return '数据库连接或SQL执行失败';
}

function base_status(array $config): array
{
    return [
        'host' => $config['host'],
        'port' => (int)$config['port'],
        'database' => $config['database'],
        'user' => $config['user'],
        'connected' => false,
        'database_exists' => false,
        'tables_ready' => false,
        'seed_ready' => false,
        'tables' => [],
        'category_count' => 0,
        'category_schema_ready' => false,
        'error_type' => '',
        'error_message' => '',
    ];
}

function required_tables(): array
{
    return ['users', 'categories', 'ledger_records', 'monthly_budgets'];
}

function inspect_database(PDO $pdo): array
{
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    sort($tables);

    $missing = array_values(array_diff(required_tables(), $tables));
    $categoryCount = 0;
    $categorySchemaReady = false;
    if (in_array('categories', $tables, true)) {
        $categoryCount = (int)$pdo->query('SELECT COUNT(*) FROM `categories`')->fetchColumn();
        $hasUserId = $pdo->query("SHOW COLUMNS FROM `categories` LIKE 'user_id'")->fetchAll() !== [];
        $hasUserIndex = $pdo->query("SHOW INDEX FROM `categories` WHERE Key_name = 'idx_user_type_status_sort'")->fetchAll() !== [];
        $categorySchemaReady = $hasUserId && $hasUserIndex;
    }

    return [
        'tables' => $tables,
        'missing_tables' => $missing,
        'category_count' => $categoryCount,
        'category_schema_ready' => $categorySchemaReady,
        'tables_ready' => $missing === [] && $categorySchemaReady,
        'seed_ready' => $categoryCount >= 8,
    ];
}

function print_json_and_exit(array $status): void
{
    echo json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), PHP_EOL;
    exit(($status['connected'] ?? false) && ($status['database_exists'] ?? false) && ($status['tables_ready'] ?? false) && ($status['seed_ready'] ?? false) ? 0 : 1);
}
