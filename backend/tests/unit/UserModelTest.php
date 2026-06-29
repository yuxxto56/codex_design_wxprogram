<?php
declare(strict_types=1);

use app\model\UserModel;

final class FakeUserPdo
{
    public array $users = [];
    private int $nextId = 1;
    private int $lastInsertId = 0;

    public function prepare(string $sql): FakeUserStatement
    {
        return new FakeUserStatement($this, $sql);
    }

    public function lastInsertId(): string
    {
        return (string)$this->lastInsertId;
    }

    public function findByOpenid(string $openid): ?array
    {
        foreach ($this->users as $user) {
            if ($user['openid'] === $openid) {
                return $user;
            }
        }

        return null;
    }

    public function findById(int $id): ?array
    {
        return $this->users[$id] ?? null;
    }

    public function insertUser(string $openid, string $nickname, string $avatar, int $now): void
    {
        $id = $this->nextId++;
        $this->lastInsertId = $id;
        $this->users[$id] = [
            'id' => $id,
            'openid' => $openid,
            'nickname' => $nickname,
            'avatar' => $avatar,
            'status' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}

final class FakeUserStatement
{
    private FakeUserPdo $pdo;
    private string $sql;
    private ?array $row = null;

    public function __construct(FakeUserPdo $pdo, string $sql)
    {
        $this->pdo = $pdo;
        $this->sql = $sql;
    }

    public function execute(array $params): bool
    {
        if (strpos($this->sql, 'WHERE `openid` = ?') !== false) {
            $this->row = $this->pdo->findByOpenid((string)$params[0]);
            return true;
        }
        if (strpos($this->sql, 'WHERE `id` = ?') !== false) {
            $this->row = $this->pdo->findById((int)$params[0]);
            return true;
        }
        if (strpos($this->sql, 'INSERT INTO `users`') !== false) {
            $this->pdo->insertUser((string)$params[0], (string)$params[1], (string)$params[2], (int)$params[3]);
            return true;
        }

        return true;
    }

    public function fetch()
    {
        return $this->row ?: false;
    }
}

$runner->test('user model persists login user through database storage', function () use ($runner): void {
    $pdo = new FakeUserPdo();
    $loginModel = new UserModel($pdo);

    $created = $loginModel->findOrCreateByOpenid('openid_profile_user');

    $reflection = new ReflectionClass(UserModel::class);
    $usersProperty = $reflection->getProperty('users');
    $usersProperty->setAccessible(true);
    $usersProperty->setValue([]);
    $nextIdProperty = $reflection->getProperty('nextId');
    $nextIdProperty->setAccessible(true);
    $nextIdProperty->setValue(1);

    $profileModel = new UserModel($pdo);
    $found = $profileModel->findById((int)$created['id']);

    $runner->assertSame('openid_profile_user', $found['openid'] ?? null);
});
