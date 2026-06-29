<?php
declare(strict_types=1);

namespace app\controller;

use app\common\BaseController;
use app\logic\BudgetLogic;
use Throwable;

/**
 * 月预算 API 控制器。
 *
 * 提供读取和保存用户月总预算的接口入口，业务校验交给 BudgetLogic。
 */
final class BudgetController extends BaseController
{
    private BudgetLogic $logic;

    public function __construct(?BudgetLogic $logic = null)
    {
        $this->logic = $logic ?? new BudgetLogic();
    }

    /**
     * 获取指定月份预算。
     *
     * 从 GET query 逐个读取 month，格式 YYYY-MM。
     */
    public function month(): array
    {
        try {
            return $this->success($this->logic->getMonthlyBudget($this->currentUserId(), $this->getRequiredString('month')));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }

    /**
     * 保存指定月份预算。
     *
     * 从 POST body 逐个读取 month 和 amount。
     */
    public function save(): array
    {
        try {
            return $this->success($this->logic->saveMonthlyBudget($this->currentUserId(), $this->postRequiredString('month'), $this->postRequiredString('amount')));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
