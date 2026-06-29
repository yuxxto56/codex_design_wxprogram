<?php
declare(strict_types=1);

namespace app\controller;

use app\common\BaseController;
use app\logic\HomeLogic;
use Throwable;

/**
 * 首页 API 控制器。
 *
 * 聚合首页所需的本月支出、收入、预算、剩余预算和最近记录。
 */
final class HomeController extends BaseController
{
    private HomeLogic $logic;

    public function __construct(?HomeLogic $logic = null)
    {
        $this->logic = $logic ?? new HomeLogic();
    }

    /**
     * 获取首页汇总数据。
     *
     * 从 GET query 逐个读取 month，格式 YYYY-MM。
     */
    public function summary(): array
    {
        try {
            return $this->success($this->logic->summary($this->currentUserId(), $this->getRequiredString('month')));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
