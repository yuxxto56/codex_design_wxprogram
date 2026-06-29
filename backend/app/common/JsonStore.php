<?php
declare(strict_types=1);

namespace app\common;

/**
 * 本地开发 JSON 存储。
 *
 * 仅用于 PHP 内置服务器开发联调，让 mock API 请求之间能保留用户、预算和账单数据。
 * 生产环境接 MySQL 后应由 Model 的数据库实现替代它。
 */
final class JsonStore
{
    /**
     * 判断是否启用开发 JSON 存储。
     */
    public static function enabled(): bool
    {
        return (string)getenv('DEV_STORE_PATH') !== '';
    }

    /**
     * 从 JSON 存储读取指定分区数据。
     *
     * @param string $key 分区名，例如 users、records、budgets。
     * @param array $default 分区不存在时的默认值。
     */
    public static function read(string $key, array $default = []): array
    {
        $data = self::all();
        return is_array($data[$key] ?? null) ? $data[$key] : $default;
    }

    /**
     * 写入指定分区数据到 JSON 文件。
     *
     * @param string $key 分区名。
     * @param array $value 要保存的数据。
     */
    public static function write(string $key, array $value): void
    {
        $data = self::all();
        $data[$key] = $value;
        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
    }

    /**
     * 读取完整 JSON 存储文件。
     */
    private static function all(): array
    {
        $path = self::path();
        if ($path === '' || !is_file($path)) {
            return [];
        }
        $data = json_decode(file_get_contents($path) ?: '{}', true);
        return is_array($data) ? $data : [];
    }

    /**
     * 获取开发存储文件路径。
     */
    private static function path(): string
    {
        return (string)getenv('DEV_STORE_PATH');
    }
}
