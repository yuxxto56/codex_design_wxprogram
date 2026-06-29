<?php
declare(strict_types=1);

namespace app\model;

use app\common\JsonStore;

/**
 * 月预算数据模型。
 *
 * 对应 MySQL 的 monthly_budgets 表；当前开发 API 使用 JsonStore 做本地模拟存储。
 */
final class BudgetModel
{
    public const FIELD_USER_ID = 'user_id';
    public const FIELD_MONTH = 'month';
    public const FIELD_AMOUNT = 'amount';

    private array $budgets = [];

    /**
     * 保存或覆盖用户某个月份的预算。
     *
     * @param int $userId 用户 ID。
     * @param string $month 月份，格式 YYYY-MM。
     * @param int $amount 预算金额，单位分。
     */
    public function save(int $userId, string $month, int $amount): array
    {
        $this->load();
        $key = $this->key($userId, $month);
        $this->budgets[$key] = [
            self::FIELD_USER_ID => $userId,
            self::FIELD_MONTH => $month,
            self::FIELD_AMOUNT => $amount,
            'updated_at' => time(),
        ];
        $this->saveStore();

        return $this->budgets[$key];
    }

    /**
     * 查询用户指定月份预算。
     *
     * @param int $userId 用户 ID。
     * @param string $month 月份，格式 YYYY-MM。
     * @return array|null 找不到时返回 null。
     */
    public function findByUserAndMonth(int $userId, string $month): ?array
    {
        $this->load();
        return $this->budgets[$this->key($userId, $month)] ?? null;
    }

    /**
     * 生成开发存储中的预算唯一键。
     */
    private function key(int $userId, string $month): string
    {
        return $userId . ':' . $month;
    }

    /**
     * 从开发 JSON 存储加载预算数据。
     */
    private function load(): void
    {
        if (JsonStore::enabled()) {
            $this->budgets = JsonStore::read('budgets');
        }
    }

    /**
     * 保存预算数据到开发 JSON 存储。
     */
    private function saveStore(): void
    {
        if (JsonStore::enabled()) {
            JsonStore::write('budgets', $this->budgets);
        }
    }
}
