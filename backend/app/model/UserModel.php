<?php
declare(strict_types=1);

namespace app\model;

use app\common\Database;

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

    /** @var object|null */
    private $pdo;

    /**
     * @param object|null $pdo 数据库连接，生产默认从环境变量创建，测试可注入兼容对象。
     */
    public function __construct($pdo = null)
    {
        $this->pdo = $pdo;
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
        $user = $this->findByOpenidFromDatabase($openid);
        if ($user !== null) {
            return $user;
        }

        return $this->createUserInDatabase($openid, $nickname, $avatar);
    }

    /**
     * 按用户 ID 查询用户。
     *
     * @param int $id 用户 ID。
     * @return array|null 找不到时返回 null。
     */
    public function findById(int $id): ?array
    {
        return $this->findByIdFromDatabase($id);
    }

    /**
     * 更新当前用户资料。
     */
    public function updateProfile(int $id, string $nickname, string $avatar): ?array
    {
        $now = time();
        $statement = $this->database()->prepare(
            'UPDATE `users` SET `nickname` = ?, `avatar` = ?, `updated_at` = ? WHERE `id` = ? AND `status` = 1'
        );
        $statement->execute([$nickname, $avatar, $now, $id]);
        if ($statement->rowCount() < 1) {
            return null;
        }

        return $this->findByIdFromDatabase($id);
    }

    /**
     * 更新当前用户头像。
     */
    public function updateAvatar(int $id, string $avatar): ?array
    {
        $now = time();
        $statement = $this->database()->prepare(
            'UPDATE `users` SET `avatar` = ?, `updated_at` = ? WHERE `id` = ? AND `status` = 1'
        );
        $statement->execute([$avatar, $now, $id]);
        if ($statement->rowCount() < 1) {
            return null;
        }

        return $this->findByIdFromDatabase($id);
    }

    /**
     * 获取用户数据库连接。
     *
     * @return object PDO 或测试注入的兼容对象。
     */
    private function database()
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $this->pdo = Database::connection();
        return $this->pdo;
    }

    /**
     * 根据 openid 从 users 表查询用户。
     */
    private function findByOpenidFromDatabase(string $openid): ?array
    {
        $statement = $this->database()->prepare('SELECT `id`, `openid`, `nickname`, `avatar`, `created_at` FROM `users` WHERE `openid` = ? AND `status` = 1 LIMIT 1');
        $statement->execute([$openid]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /**
     * 根据用户 ID 从 users 表查询用户。
     */
    private function findByIdFromDatabase(int $id): ?array
    {
        $statement = $this->database()->prepare('SELECT `id`, `openid`, `nickname`, `avatar`, `created_at` FROM `users` WHERE `id` = ? AND `status` = 1 LIMIT 1');
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
        $pdo = $this->database();
        $statement = $pdo->prepare('INSERT INTO `users` (`openid`, `nickname`, `avatar`, `status`, `created_at`, `updated_at`) VALUES (?, ?, ?, 1, ?, ?)');
        $statement->execute([$openid, $nickname, $avatar, $now, $now]);

        return $this->findByIdFromDatabase((int)$pdo->lastInsertId()) ?? [
            self::FIELD_ID => (int)$pdo->lastInsertId(),
            self::FIELD_OPENID => $openid,
            self::FIELD_NICKNAME => $nickname,
            self::FIELD_AVATAR => $avatar,
            'created_at' => $now,
        ];
    }
}
