<?php
declare(strict_types=1);

namespace app\model;

use app\common\JsonStore;
use PDO;

/**
 * 用户数据模型。
 *
 * 对应 MySQL 的 users 表；通过 openid 查找或创建微信小程序用户。
 */
final class UserModel
{
    public const FIELD_ID = 'id';
    public const FIELD_OPENID = 'openid';
    public const FIELD_NICKNAME = 'nickname';
    public const FIELD_AVATAR = 'avatar';

    private static array $users = [];
    private static int $nextId = 1;
    /** @var object|null */
    private $pdo;

    /**
     * @param object|null $pdo 数据库连接，生产默认从环境变量创建，测试可注入兼容对象。
     */
    public function __construct($pdo = null)
    {
        $this->pdo = $pdo ?? $this->createPdoFromEnv();
    }

    /**
     * 根据 openid 查找用户，不存在时创建默认用户。
     *
     * @param string $openid 微信小程序 openid。
     * @param string $nickname 默认昵称。
     * @param string $avatar 默认头像 URL。
     * @return array 用户数据。
     */
    public function findOrCreateByOpenid(string $openid, string $nickname = '糯米豆', string $avatar = ''): array
    {
        if ($this->databaseEnabled()) {
            $user = $this->findByOpenidFromDatabase($openid);
            if ($user !== null) {
                return $user;
            }

            return $this->createUserInDatabase($openid, $nickname, $avatar);
        }

        $this->load();
        foreach (self::$users as $user) {
            if ($user[self::FIELD_OPENID] === $openid) {
                return $user;
            }
        }

        $user = [
            self::FIELD_ID => self::$nextId++,
            self::FIELD_OPENID => $openid,
            self::FIELD_NICKNAME => $nickname,
            self::FIELD_AVATAR => $avatar,
            'created_at' => time(),
        ];
        self::$users[] = $user;
        $this->save();

        return $user;
    }

    /**
     * 按用户 ID 查询用户。
     *
     * @param int $id 用户 ID。
     * @return array|null 找不到时返回 null。
     */
    public function findById(int $id): ?array
    {
        if ($this->databaseEnabled()) {
            return $this->findByIdFromDatabase($id);
        }

        $this->load();
        foreach (self::$users as $user) {
            if ((int)$user[self::FIELD_ID] === $id) {
                return $user;
            }
        }

        return null;
    }

    /**
     * 从开发 JSON 存储加载用户数据和自增 ID。
     */
    private function load(): void
    {
        if (!JsonStore::enabled()) {
            return;
        }
        self::$users = JsonStore::read('users');
        $meta = JsonStore::read('meta', ['next_user_id' => 1]);
        self::$nextId = (int)($meta['next_user_id'] ?? (count(self::$users) + 1));
    }

    /**
     * 保存用户数据和自增 ID 到开发 JSON 存储。
     */
    private function save(): void
    {
        if (!JsonStore::enabled()) {
            return;
        }
        $meta = JsonStore::read('meta');
        $meta['next_user_id'] = self::$nextId;
        JsonStore::write('users', self::$users);
        JsonStore::write('meta', $meta);
    }

    /**
     * 当前模型是否使用数据库存储。
     */
    private function databaseEnabled(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * 从环境变量创建 MySQL PDO 连接。
     *
     * 未配置数据库环境时返回 null，保留本地 JSON/内存 fallback。
     */
    private function createPdoFromEnv(): ?PDO
    {
        $host = getenv('DATABASE_HOST') ?: getenv('MYSQL_HOST') ?: '';
        $database = getenv('DATABASE_NAME') ?: getenv('MYSQL_DATABASE') ?: '';
        $user = getenv('DATABASE_USER') ?: getenv('MYSQL_USERNAME') ?: '';
        if ($host === '' || $database === '' || $user === '' || !extension_loaded('pdo_mysql')) {
            return null;
        }

        $port = getenv('DATABASE_PORT') ?: getenv('MYSQL_PORT') ?: '3306';
        $password = getenv('DATABASE_PASS') ?: getenv('MYSQL_PASSWORD') ?: '';
        $charset = getenv('DATABASE_CHARSET') ?: getenv('MYSQL_CHARSET') ?: 'utf8mb4';
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        return new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 8,
        ]);
    }

    /**
     * 根据 openid 从 users 表查询用户。
     */
    private function findByOpenidFromDatabase(string $openid): ?array
    {
        $statement = $this->pdo->prepare('SELECT `id`, `openid`, `nickname`, `avatar`, `created_at` FROM `users` WHERE `openid` = ? AND `status` = 1 LIMIT 1');
        $statement->execute([$openid]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /**
     * 根据用户 ID 从 users 表查询用户。
     */
    private function findByIdFromDatabase(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT `id`, `openid`, `nickname`, `avatar`, `created_at` FROM `users` WHERE `id` = ? AND `status` = 1 LIMIT 1');
        $statement->execute([$id]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /**
     * 新增微信用户到 users 表。
     */
    private function createUserInDatabase(string $openid, string $nickname, string $avatar): array
    {
        $now = time();
        $statement = $this->pdo->prepare('INSERT INTO `users` (`openid`, `nickname`, `avatar`, `status`, `created_at`, `updated_at`) VALUES (?, ?, ?, 1, ?, ?)');
        $statement->execute([$openid, $nickname, $avatar, $now, $now]);

        return $this->findByIdFromDatabase((int)$this->pdo->lastInsertId()) ?? [
            self::FIELD_ID => (int)$this->pdo->lastInsertId(),
            self::FIELD_OPENID => $openid,
            self::FIELD_NICKNAME => $nickname,
            self::FIELD_AVATAR => $avatar,
            'created_at' => $now,
        ];
    }
}
