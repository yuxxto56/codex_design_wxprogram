<?php
declare(strict_types=1);

namespace app\model;

use app\common\JsonStore;

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
}
