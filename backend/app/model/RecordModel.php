<?php
declare(strict_types=1);

namespace app\model;

use app\common\Database;
use DateTimeImmutable;
use DateTimeZone;

/**
 * 收支记录数据模型。
 *
 * 对应 MySQL 的 ledger_records 表；封装新增、修改、删除、月度查询和汇总统计。
 */
final class RecordModel
{
    public const FIELD_ID = 'id';
    public const FIELD_USER_ID = 'user_id';
    public const FIELD_TYPE = 'type';
    public const FIELD_CATEGORY_ID = 'category_id';
    public const FIELD_AMOUNT = 'amount';
    public const FIELD_REMARK = 'remark';
    public const FIELD_HAPPENED_AT = 'happened_at';
    public const FIELD_CREATED_AT = 'created_at';
    public const TYPE_EXPENSE = 1;
    public const TYPE_INCOME = 2;

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
     * 新增一条收支记录。
     *
     * @param array $data 标准化后的记录数据，amount 单位为分。
     * @return array 带自增 ID 和创建时间的记录。
     */
    public function create(array $data): array
    {
        $now = time();
        $createdAt = (int)($data[self::FIELD_CREATED_AT] ?? $now);
        $statement = $this->database()->prepare(
            'INSERT INTO `ledger_records` (`user_id`, `type`, `category_id`, `amount`, `remark`, `happened_at`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $statement->execute([
            (int)$data[self::FIELD_USER_ID],
            (int)$data[self::FIELD_TYPE],
            (int)$data[self::FIELD_CATEGORY_ID],
            (int)$data[self::FIELD_AMOUNT],
            (string)($data[self::FIELD_REMARK] ?? ''),
            (int)$data[self::FIELD_HAPPENED_AT],
            $createdAt,
            $now,
        ]);

        $id = (int)$this->database()->lastInsertId();
        return $this->findByUser((int)$data[self::FIELD_USER_ID], $id) ?? [
            self::FIELD_ID => $id,
            self::FIELD_USER_ID => (int)$data[self::FIELD_USER_ID],
            self::FIELD_TYPE => (int)$data[self::FIELD_TYPE],
            self::FIELD_CATEGORY_ID => (int)$data[self::FIELD_CATEGORY_ID],
            self::FIELD_AMOUNT => (int)$data[self::FIELD_AMOUNT],
            self::FIELD_REMARK => (string)($data[self::FIELD_REMARK] ?? ''),
            self::FIELD_HAPPENED_AT => (int)$data[self::FIELD_HAPPENED_AT],
            self::FIELD_CREATED_AT => $createdAt,
            'updated_at' => $now,
        ];
    }

    /**
     * 更新当前用户自己的记录。
     *
     * @param int $userId 用户 ID。
     * @param int $id 记录 ID。
     * @param array $data 待更新字段。
     * @return array|null 找不到或不属于该用户时返回 null。
     */
    public function updateByUser(int $userId, int $id, array $data): ?array
    {
        $statement = $this->database()->prepare(
            'UPDATE `ledger_records` SET `type` = ?, `category_id` = ?, `amount` = ?, `remark` = ?, `happened_at` = ?, `updated_at` = ? WHERE `id` = ? AND `user_id` = ?'
        );
        $statement->execute([
            (int)$data[self::FIELD_TYPE],
            (int)$data[self::FIELD_CATEGORY_ID],
            (int)$data[self::FIELD_AMOUNT],
            (string)($data[self::FIELD_REMARK] ?? ''),
            (int)$data[self::FIELD_HAPPENED_AT],
            time(),
            $id,
            $userId,
        ]);
        if ($statement->rowCount() < 1) {
            return null;
        }

        return $this->findByUser($userId, $id);
    }

    /**
     * 删除当前用户自己的记录。
     *
     * @param int $userId 用户 ID。
     * @param int $id 记录 ID。
     * @return bool 是否删除成功。
     */
    public function deleteByUser(int $userId, int $id): bool
    {
        $statement = $this->database()->prepare('DELETE FROM `ledger_records` WHERE `id` = ? AND `user_id` = ?');
        $statement->execute([$id, $userId]);
        return $statement->rowCount() > 0;
    }

    /**
     * 查询当前用户的一条记录。
     *
     * @param int $userId 用户 ID。
     * @param int $id 记录 ID。
     */
    public function findByUser(int $userId, int $id): ?array
    {
        $statement = $this->database()->prepare(
            'SELECT `id`, `user_id`, `type`, `category_id`, `amount`, `remark`, `happened_at`, `created_at`, `updated_at` FROM `ledger_records` WHERE `id` = ? AND `user_id` = ? LIMIT 1'
        );
        $statement->execute([$id, $userId]);
        $record = $statement->fetch();

        return is_array($record) ? $record : null;
    }

    /**
     * 查询用户某个月份的全部记录。
     *
     * @param int $userId 用户 ID。
     * @param string $month 月份，格式 YYYY-MM。
     * @return array 按发生时间倒序排列的记录列表。
     */
    public function listByMonth(int $userId, string $month): array
    {
        [$start, $end] = $this->monthRange($month);
        $statement = $this->database()->prepare(
            'SELECT `id`, `user_id`, `type`, `category_id`, `amount`, `remark`, `happened_at`, `created_at`, `updated_at` FROM `ledger_records` WHERE `user_id` = ? AND `happened_at` >= ? AND `happened_at` < ? ORDER BY `happened_at` DESC, `id` DESC'
        );
        $statement->execute([$userId, $start, $end]);

        return $statement->fetchAll();
    }

    /**
     * 统计用户某个月份某种类型的总金额。
     *
     * @param int $userId 用户 ID。
     * @param string $month 月份，格式 YYYY-MM。
     * @param int $type 收支类型：1支出，2收入。
     * @return int 总金额，单位分。
     */
    public function sumByMonthAndType(int $userId, string $month, int $type): int
    {
        [$start, $end] = $this->monthRange($month);
        $statement = $this->database()->prepare(
            'SELECT COALESCE(SUM(`amount`), 0) AS `total_amount` FROM `ledger_records` WHERE `user_id` = ? AND `happened_at` >= ? AND `happened_at` < ? AND `type` = ?'
        );
        $statement->execute([$userId, $start, $end, $type]);
        $row = $statement->fetch();

        return (int)($row['total_amount'] ?? 0);
    }

    /**
     * 获取用户某个月份最近的记录。
     *
     * @param int $userId 用户 ID。
     * @param string $month 月份，格式 YYYY-MM。
     * @param int $limit 返回数量。
     */
    public function recentByMonth(int $userId, string $month, int $limit): array
    {
        return array_slice($this->listByMonth($userId, $month), 0, $limit);
    }

    /**
     * 查询用户最新一条记录的发生时间。
     *
     * @param int $userId 用户 ID。
     * @return int 最新记录发生时间，未记录时返回 0。
     */
    public function latestHappenedAtByUser(int $userId): int
    {
        $statement = $this->database()->prepare(
            'SELECT COALESCE(MAX(`happened_at`), 0) AS `latest_happened_at` FROM `ledger_records` WHERE `user_id` = ?'
        );
        $statement->execute([$userId]);
        $row = $statement->fetch();

        return (int)($row['latest_happened_at'] ?? 0);
    }

    /**
     * 获取记录数据库连接。
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

    private function monthRange(string $month): array
    {
        $timezone = new DateTimeZone('Asia/Shanghai');
        $start = new DateTimeImmutable($month . '-01 00:00:00', $timezone);
        return [$start->getTimestamp(), $start->modify('+1 month')->getTimestamp()];
    }
}
