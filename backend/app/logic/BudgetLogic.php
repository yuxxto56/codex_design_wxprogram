<?php
declare(strict_types=1);

namespace app\logic;

use app\common\BusinessException;
use app\common\ErrorCode;
use app\common\Money;
use app\model\BudgetModel;

/**
 * 月预算业务逻辑。
 *
 * 负责校验月份格式、金额格式，并把前端传入的元转换为后端存储的分。
 */
final class BudgetLogic
{
    private BudgetModel $budgetModel;

    public function __construct(?BudgetModel $budgetModel = null)
    {
        $this->budgetModel = $budgetModel ?? new BudgetModel();
    }

    /**
     * 保存用户某个月份的总预算。
     *
     * @param int $userId 用户 ID。
     * @param string $month 月份，格式 YYYY-MM。
     * @param string $amount 元格式金额，例如 1800.00。
     * @return array 保存后的预算数据，amount 为分。
     */
    public function saveMonthlyBudget(int $userId, string $month, string $amount): array
    {
        $this->assertMonth($month);
        return $this->budgetModel->save($userId, $month, Money::yuanToCent($amount));
    }

    /**
     * 获取用户某个月份的预算。
     *
     * 未设置预算时返回 amount=0，方便前端直接展示默认状态。
     *
     * @param int $userId 用户 ID。
     * @param string $month 月份，格式 YYYY-MM。
     */
    public function getMonthlyBudget(int $userId, string $month): array
    {
        $this->assertMonth($month);
        return $this->budgetModel->findByUserAndMonth($userId, $month) ?? [
            'user_id' => $userId,
            'month' => $month,
            'amount' => 0,
        ];
    }

    /**
     * 校验月份参数格式。
     *
     * @param string $month 月份，必须为 YYYY-MM。
     */
    private function assertMonth(string $month): void
    {
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '月份格式错误');
        }
    }
}
