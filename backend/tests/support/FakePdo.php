<?php
declare(strict_types=1);

final class FakePdo
{
    public array $users = [];
    public array $categories = [];
    public array $records = [];
    public array $budgets = [];
    private array $nextIds = [
        'users' => 1,
        'categories' => 1000,
        'ledger_records' => 1,
        'monthly_budgets' => 1,
    ];
    private int $lastInsertId = 0;

    public function __construct()
    {
        $this->categories = [
            ['id' => 1, 'user_id' => 0, 'type' => 1, 'name' => '餐饮', 'icon' => 'restaurant', 'color' => '#F2E1BB', 'sort' => 10, 'status' => 1],
            ['id' => 2, 'user_id' => 0, 'type' => 1, 'name' => '购物', 'icon' => 'shopping_bag', 'color' => '#FFB7C5', 'sort' => 20, 'status' => 1],
            ['id' => 101, 'user_id' => 0, 'type' => 2, 'name' => '工资', 'icon' => 'payments', 'color' => '#C8ECC9', 'sort' => 10, 'status' => 1],
        ];
    }

    public function prepare(string $sql): FakeStatement
    {
        return new FakeStatement($this, $sql);
    }

    public function lastInsertId(): string
    {
        return (string)$this->lastInsertId;
    }

    public function setLastInsertId(int $id): void
    {
        $this->lastInsertId = $id;
    }

    public function nextId(string $table): int
    {
        return $this->nextIds[$table]++;
    }
}

final class FakeStatement
{
    private FakePdo $pdo;
    private string $sql;
    private array $rows = [];
    private int $rowCount = 0;

    public function __construct(FakePdo $pdo, string $sql)
    {
        $this->pdo = $pdo;
        $this->sql = $sql;
    }

    public function execute(array $params = []): bool
    {
        $this->rows = [];
        $this->rowCount = 0;

        if (strpos($this->sql, 'FROM `users` WHERE `openid` = ?') !== false) {
            foreach ($this->pdo->users as $user) {
                if ($user['openid'] === (string)$params[0] && (int)$user['status'] === 1) {
                    $this->rows = [$user];
                    break;
                }
            }
            return true;
        }

        if (strpos($this->sql, 'FROM `users` WHERE `id` = ?') !== false) {
            $user = $this->pdo->users[(int)$params[0]] ?? null;
            $this->rows = $user !== null && (int)$user['status'] === 1 ? [$user] : [];
            return true;
        }

        if (strpos($this->sql, 'INSERT INTO `users`') !== false) {
            $id = $this->pdo->nextId('users');
            $this->pdo->setLastInsertId($id);
            $this->pdo->users[$id] = [
                'id' => $id,
                'openid' => (string)$params[0],
                'nickname' => (string)$params[1],
                'avatar' => (string)$params[2],
                'status' => 1,
                'created_at' => (int)$params[3],
                'updated_at' => (int)$params[4],
            ];
            $this->rowCount = 1;
            return true;
        }

        if (strpos($this->sql, 'UPDATE `users` SET `avatar` = ?') !== false) {
            $id = (int)$params[2];
            if (isset($this->pdo->users[$id]) && (int)$this->pdo->users[$id]['status'] === 1) {
                $this->pdo->users[$id] = array_merge($this->pdo->users[$id], [
                    'avatar' => (string)$params[0],
                    'updated_at' => (int)$params[1],
                ]);
                $this->rowCount = 1;
            }
            return true;
        }

        if (strpos($this->sql, 'UPDATE `users` SET') !== false) {
            $id = (int)$params[3];
            if (isset($this->pdo->users[$id]) && (int)$this->pdo->users[$id]['status'] === 1) {
                $this->pdo->users[$id] = array_merge($this->pdo->users[$id], [
                    'nickname' => (string)$params[0],
                    'avatar' => (string)$params[1],
                    'updated_at' => (int)$params[2],
                ]);
                $this->rowCount = 1;
            }
            return true;
        }

        if (strpos($this->sql, 'FROM `categories` WHERE `status` = 1') !== false) {
            $userId = array_key_exists(0, $params) ? (int)$params[0] : 0;
            $rows = array_values(array_filter($this->pdo->categories, static function (array $row) use ($userId): bool {
                return (int)$row['status'] === 1 && ((int)($row['user_id'] ?? 0) === 0 || (int)($row['user_id'] ?? 0) === $userId);
            }));
            usort($rows, static fn(array $a, array $b): int => ((int)$a['type'] <=> (int)$b['type']) ?: ((int)$a['sort'] <=> (int)$b['sort']));
            $this->rows = $rows;
            return true;
        }

        if (strpos($this->sql, 'FROM `categories` WHERE `id` = ?') !== false) {
            $userId = array_key_exists(2, $params) ? (int)$params[2] : 0;
            foreach ($this->pdo->categories as $category) {
                if ((int)$category['id'] === (int)$params[0]
                    && (int)$category['type'] === (int)$params[1]
                    && (int)$category['status'] === 1
                    && ((int)($category['user_id'] ?? 0) === 0 || (int)($category['user_id'] ?? 0) === $userId)) {
                    $this->rows = [$category];
                    break;
                }
            }
            return true;
        }

        if (strpos($this->sql, 'INSERT INTO `categories`') !== false) {
            $id = $this->pdo->nextId('categories');
            $this->pdo->setLastInsertId($id);
            $this->pdo->categories[] = [
                'id' => $id,
                'user_id' => (int)$params[0],
                'type' => (int)$params[1],
                'name' => (string)$params[2],
                'icon' => (string)$params[3],
                'color' => (string)$params[4],
                'sort' => (int)$params[5],
                'status' => 1,
                'created_at' => (int)$params[6],
                'updated_at' => (int)$params[7],
            ];
            $this->rowCount = 1;
            return true;
        }

        if (strpos($this->sql, 'INSERT INTO `ledger_records`') !== false) {
            $id = $this->pdo->nextId('ledger_records');
            $this->pdo->setLastInsertId($id);
            $this->pdo->records[$id] = [
                'id' => $id,
                'user_id' => (int)$params[0],
                'type' => (int)$params[1],
                'category_id' => (int)$params[2],
                'amount' => (int)$params[3],
                'remark' => (string)$params[4],
                'happened_at' => (int)$params[5],
                'created_at' => (int)$params[6],
                'updated_at' => (int)$params[7],
            ];
            $this->rowCount = 1;
            return true;
        }

        if (strpos($this->sql, 'UPDATE `ledger_records`') !== false) {
            foreach ($this->pdo->records as $id => $record) {
                if ((int)$record['id'] === (int)$params[6] && (int)$record['user_id'] === (int)$params[7]) {
                    $this->pdo->records[$id] = array_merge($record, [
                        'type' => (int)$params[0],
                        'category_id' => (int)$params[1],
                        'amount' => (int)$params[2],
                        'remark' => (string)$params[3],
                        'happened_at' => (int)$params[4],
                        'updated_at' => (int)$params[5],
                    ]);
                    $this->rowCount = 1;
                    break;
                }
            }
            return true;
        }

        if (strpos($this->sql, 'DELETE FROM `ledger_records`') !== false) {
            foreach ($this->pdo->records as $id => $record) {
                if ((int)$record['id'] === (int)$params[0] && (int)$record['user_id'] === (int)$params[1]) {
                    unset($this->pdo->records[$id]);
                    $this->rowCount = 1;
                    break;
                }
            }
            return true;
        }

        if (strpos($this->sql, 'FROM `ledger_records` WHERE `id` = ?') !== false) {
            $record = $this->pdo->records[(int)$params[0]] ?? null;
            $this->rows = $record !== null && (int)$record['user_id'] === (int)$params[1] ? [$record] : [];
            return true;
        }

        if (strpos($this->sql, 'MAX(`happened_at`)') !== false) {
            $latest = 0;
            foreach ($this->pdo->records as $record) {
                if ((int)$record['user_id'] === (int)$params[0]) {
                    $latest = max($latest, (int)$record['happened_at']);
                }
            }
            $this->rows = [['latest_happened_at' => $latest]];
            return true;
        }

        if (strpos($this->sql, 'FROM `ledger_records` WHERE `user_id` = ?') !== false) {
            $rows = array_values(array_filter($this->pdo->records, static function (array $record) use ($params): bool {
                return (int)$record['user_id'] === (int)$params[0]
                    && (int)$record['happened_at'] >= (int)$params[1]
                    && (int)$record['happened_at'] < (int)$params[2];
            }));
            if (strpos($this->sql, 'AND `type` = ?') !== false) {
                $type = (int)$params[3];
                $sum = array_reduce($rows, static fn(int $carry, array $record): int => $carry + ((int)$record['type'] === $type ? (int)$record['amount'] : 0), 0);
                $this->rows = [['total_amount' => $sum]];
                return true;
            }
            usort($rows, static fn(array $a, array $b): int => (int)$b['happened_at'] <=> (int)$a['happened_at']);
            $this->rows = $rows;
            return true;
        }

        if (strpos($this->sql, 'INSERT INTO `monthly_budgets`') !== false) {
            $key = ((int)$params[0]) . ':' . (string)$params[1];
            $existingId = null;
            foreach ($this->pdo->budgets as $id => $budget) {
                if ($key === ((int)$budget['user_id']) . ':' . (string)$budget['month']) {
                    $existingId = $id;
                    break;
                }
            }
            $id = $existingId ?? $this->pdo->nextId('monthly_budgets');
            $this->pdo->setLastInsertId($id);
            $createdAt = $this->pdo->budgets[$id]['created_at'] ?? (int)$params[3];
            $this->pdo->budgets[$id] = [
                'id' => $id,
                'user_id' => (int)$params[0],
                'month' => (string)$params[1],
                'amount' => (int)$params[2],
                'created_at' => $createdAt,
                'updated_at' => (int)$params[4],
            ];
            $this->rowCount = 1;
            return true;
        }

        if (strpos($this->sql, 'FROM `monthly_budgets`') !== false) {
            foreach ($this->pdo->budgets as $budget) {
                if ((int)$budget['user_id'] === (int)$params[0] && (string)$budget['month'] === (string)$params[1]) {
                    $this->rows = [$budget];
                    break;
                }
            }
            return true;
        }

        return true;
    }

    public function fetch()
    {
        return array_shift($this->rows) ?: false;
    }

    public function fetchAll(): array
    {
        return $this->rows;
    }

    public function fetchColumn()
    {
        $row = $this->fetch();
        if ($row === false) {
            return false;
        }
        return reset($row);
    }

    public function rowCount(): int
    {
        return $this->rowCount;
    }
}
