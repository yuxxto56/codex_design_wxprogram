<?php
declare(strict_types=1);

namespace app\model;

use app\common\JsonStore;

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

    private array $records = [];
    private int $nextId = 1;

    /**
     * 新增一条收支记录。
     *
     * @param array $data 标准化后的记录数据，amount 单位为分。
     * @return array 带自增 ID 和创建时间的记录。
     */
    public function create(array $data): array
    {
        $this->load();
        $data[self::FIELD_ID] = $this->nextId++;
        $data[self::FIELD_CREATED_AT] = $data[self::FIELD_CREATED_AT] ?? time();
        $this->records[] = $data;
        $this->save();

        return $data;
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
        $this->load();
        foreach ($this->records as $index => $record) {
            if ((int)$record[self::FIELD_ID] === $id && (int)$record[self::FIELD_USER_ID] === $userId) {
                $this->records[$index] = array_merge($record, $data);
                $this->save();
                return $this->records[$index];
            }
        }

        return null;
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
        $this->load();
        foreach ($this->records as $index => $record) {
            if ((int)$record[self::FIELD_ID] === $id && (int)$record[self::FIELD_USER_ID] === $userId) {
                array_splice($this->records, $index, 1);
                $this->save();
                return true;
            }
        }

        return false;
    }

    /**
     * 查询当前用户的一条记录。
     *
     * @param int $userId 用户 ID。
     * @param int $id 记录 ID。
     */
    public function findByUser(int $userId, int $id): ?array
    {
        $this->load();
        foreach ($this->records as $record) {
            if ((int)$record[self::FIELD_ID] === $id && (int)$record[self::FIELD_USER_ID] === $userId) {
                return $record;
            }
        }

        return null;
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
        $this->load();
        $start = strtotime($month . '-01 00:00:00');
        $end = strtotime('+1 month', $start);

        $items = array_values(array_filter($this->records, static function (array $record) use ($userId, $start, $end): bool {
            return (int)$record['user_id'] === $userId
                && (int)$record['happened_at'] >= $start
                && (int)$record['happened_at'] < $end;
        }));

        usort($items, static fn(array $a, array $b): int => $b['happened_at'] <=> $a['happened_at']);
        return $items;
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
        $sum = 0;
        foreach ($this->listByMonth($userId, $month) as $record) {
            if ((int)$record['type'] === $type) {
                $sum += (int)$record['amount'];
            }
        }

        return $sum;
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
     * 从开发 JSON 存储加载记录数据。
     */
    private function load(): void
    {
        if (!JsonStore::enabled()) {
            return;
        }
        $this->records = JsonStore::read('records');
        $meta = JsonStore::read('meta', ['next_record_id' => 1]);
        $this->nextId = (int)($meta['next_record_id'] ?? (count($this->records) + 1));
    }

    /**
     * 保存记录数据到开发 JSON 存储。
     */
    private function save(): void
    {
        if (!JsonStore::enabled()) {
            return;
        }
        $meta = JsonStore::read('meta');
        $meta['next_record_id'] = $this->nextId;
        JsonStore::write('records', $this->records);
        JsonStore::write('meta', $meta);
    }
}
