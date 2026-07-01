<?php
declare(strict_types=1);

namespace app\controller;

use app\common\BaseController;
use app\logic\UserLogic;
use Throwable;

/**
 * 用户 API 控制器。
 *
 * 提供当前登录用户资料查询和保存接口。
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

    /**
     * 保存当前用户昵称和头像。
     */
    public function saveProfile(): array
    {
        try {
            return $this->success($this->logic->saveProfile($this->currentUserId(), [
                'nickname' => $this->postRequiredString('nickname'),
                'avatar' => $this->postOptionalString('avatar'),
            ]));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }

    /**
     * 上传并保存当前用户头像。
     */
    public function uploadAvatar(): array
    {
        try {
            return $this->success($this->logic->uploadAvatar($this->currentUserId(), $this->uploadedFile('avatar')));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
