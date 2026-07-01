<?php
declare(strict_types=1);

namespace app\common;

use PDO;
use Throwable;

/**
 * 统一数据库连接入口。
 *
 * 生产和本地调试都从环境变量读取 MySQL 配置，模型层通过此类获取 PDO 连接。
 */
final class Database
{
    /**
     * 创建 MySQL PDO 连接。
     *
     * @throws BusinessException 数据库配置缺失、扩展缺失或连接失败时抛出统一业务异常。
     */
    public static function connection(): PDO
    {
        $host = getenv('DATABASE_HOST') ?: getenv('MYSQL_HOST') ?: '';
        $database = getenv('DATABASE_NAME') ?: getenv('MYSQL_DATABASE') ?: '';
        $user = getenv('DATABASE_USER') ?: getenv('MYSQL_USERNAME') ?: '';
        if ($host === '' || $database === '' || $user === '' || !extension_loaded('pdo_mysql')) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '数据库连接失败，请检查数据库配置');
        }

        $port = getenv('DATABASE_PORT') ?: getenv('MYSQL_PORT') ?: '3306';
        $password = getenv('DATABASE_PASS') ?: getenv('MYSQL_PASSWORD') ?: '';
        $charset = getenv('DATABASE_CHARSET') ?: getenv('MYSQL_CHARSET') ?: 'utf8mb4';
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        try {
            return new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 8,
            ]);
        } catch (Throwable $exception) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '数据库连接失败，请检查数据库配置');
        }
    }
}
