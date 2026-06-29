<?php
declare(strict_types=1);

namespace app\controller;

use app\common\BaseController;
use app\logic\CategoryLogic;
use Throwable;

/**
 * 分类 API 控制器。
 *
 * 返回系统预置的收入/支出分类，前端记账页使用该数据渲染分类选择。
 */
final class CategoryController extends BaseController
{
    private CategoryLogic $logic;

    public function __construct(?CategoryLogic $logic = null)
    {
        $this->logic = $logic ?? new CategoryLogic();
    }

    /**
     * 获取系统预置分类列表。
     */
    public function list(): array
    {
        try {
            return $this->success($this->logic->list());
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
