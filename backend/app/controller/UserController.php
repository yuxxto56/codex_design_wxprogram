<?php
declare(strict_types=1);

namespace app\controller;

use app\common\BaseController;
use app\logic\UserLogic;
use Throwable;

/**
 * 用户 API 控制器。
 *
 * 提供当前登录用户资料查询接口。
 */
final class UserController extends BaseController
{
    private UserLogic $logic;

    public function __construct(?UserLogic $logic = null)
    {
        $this->logic = $logic ?? new UserLogic();
    }

    /**
     * 获取当前用户资料。
     *
     * 从请求上下文读取当前登录用户 ID。
     */
    public function profile(): array
    {
        try {
            return $this->success($this->logic->profile($this->currentUserId()));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
