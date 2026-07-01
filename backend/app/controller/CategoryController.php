<?php
declare(strict_types=1);

namespace app\controller;

use app\common\BaseController;
use app\logic\CategoryLogic;
use Throwable;

/**
 * 分类 API 控制器。
 *
 * 返回系统预置和用户自定义的收入/支出分类，前端记账页使用该数据渲染分类选择。
 */
final class CategoryController extends BaseController
{
    private CategoryLogic $logic;

    public function __construct(?CategoryLogic $logic = null)
    {
        $this->logic = $logic ?? new CategoryLogic();
    }

    /**
     * 获取系统预置和当前用户自定义分类列表。
     */
    public function list(): array
    {
        try {
            return $this->success($this->logic->list($this->currentUserId()));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }

    /**
     * 新增当前用户自定义分类。
     */
    public function create(): array
    {
        try {
            return $this->success($this->logic->create($this->currentUserId(), [
                'type' => $this->postInt('type'),
                'name' => $this->postRequiredString('name'),
            ]));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
