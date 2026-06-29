<?php
declare(strict_types=1);

namespace app\logic;

use app\model\BudgetModel;
use app\model\CategoryModel;
use app\model\RecordModel;

/**
 * 首页汇总业务逻辑。
 *
 * 分阶段读取账单、预算和分类数据，然后在 Logic 层组装首页展示结构。
 */
final class HomeLogic
{
    private RecordModel $recordModel;
    private BudgetModel $budgetModel;
    private CategoryModel $categoryModel;

    public function __construct(?RecordModel $recordModel = null, ?BudgetModel $budgetModel = null, ?CategoryModel $categoryModel = null)
    {
        $this->recordModel = $recordModel ?? new RecordModel();
        $this->budgetModel = $budgetModel ?? new BudgetModel();
        $this->categoryModel = $categoryModel ?? new CategoryModel();
    }

    /**
     * 获取首页月度汇总。
     *
     * @param int $userId 用户 ID。
     * @param string $month 月份，格式 YYYY-MM。
     * @return array 包含支出、收入、预算、剩余预算、已用比例、最近记录和分类。
     */
    public function summary(int $userId, string $month): array
    {
        $expense = $this->recordModel->sumByMonthAndType($userId, $month, RecordModel::TYPE_EXPENSE);
        $income = $this->recordModel->sumByMonthAndType($userId, $month, RecordModel::TYPE_INCOME);
        $budget = $this->budgetModel->findByUserAndMonth($userId, $month);
        $budgetAmount = (int)($budget['amount'] ?? 0);

        return [
            'month' => $month,
            'expense_amount' => $expense,
            'income_amount' => $income,
            'budget_amount' => $budgetAmount,
            'remaining_amount' => $budgetAmount - $expense,
            'used_percent' => $budgetAmount > 0 ? min(999, (int)round($expense * 100 / $budgetAmount)) : 0,
            'recent_records' => $this->recordModel->recentByMonth($userId, $month, 5),
            'categories' => $this->categoryModel->allActive(),
        ];
    }
}
