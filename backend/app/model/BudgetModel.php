<?php
declare(strict_types=1);

namespace app\model;

use app\common\Database;

/**
 * 月预算数据模型。
 *
 * 对应 MySQL 的 monthly_budgets 表；按 user_id + month 唯一键保存月总预算。
 */
final class BudgetModel
{
    public const FIELD_USER_ID = 'user_id';
    public const FIELD_MONTH = 'month';
    public const FIELD_AMOUNT = 'amount';

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
     * 保存或覆盖用户某个月份的预算。
     *
     * @param int $userId 用户 ID。
     * @param string $month 月份，格式 YYYY-MM。
     * @param int $amount 预算金额，单位分。
     */
    public function save(int $userId, string $month, int $amount): array
    {
        $now = time();
        $statement = $this->database()->prepare(
            'INSERT INTO `monthly_budgets` (`user_id`, `month`, `amount`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `amount` = VALUES(`amount`), `updated_at` = VALUES(`updated_at`)'
        );
        $statement->execute([$userId, $month, $amount, $now, $now]);

        return $this->findByUserAndMonth($userId, $month) ?? [
            self::FIELD_USER_ID => $userId,
            self::FIELD_MONTH => $month,
            self::FIELD_AMOUNT => $amount,
            'created_at' => $now,
            'updated_at' => $now,
        ];
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
        $statement = $this->database()->prepare(
            'SELECT `id`, `user_id`, `month`, `amount`, `created_at`, `updated_at` FROM `monthly_budgets` WHERE `user_id` = ? AND `month` = ? LIMIT 1'
        );
        $statement->execute([$userId, $month]);
        $budget = $statement->fetch();

        return is_array($budget) ? $budget : null;
    }

    /**
     * 获取预算数据库连接。
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
}
