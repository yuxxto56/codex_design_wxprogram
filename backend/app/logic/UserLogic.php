<?php
declare(strict_types=1);

namespace app\logic;

use app\common\BusinessException;
use app\common\ErrorCode;
use app\model\UserModel;

/**
 * 用户业务逻辑。
 *
 * 负责当前登录用户资料读取和用户存在性校验。
 */
final class UserLogic
{
    private UserModel $userModel;

    public function __construct(?UserModel $userModel = null)
    {
        $this->userModel = $userModel ?? new UserModel();
    }

    /**
     * 获取用户资料。
     *
     * @param int $userId 用户 ID。
     * @return array 用户信息。
     */
    public function profile(int $userId): array
    {
        $user = $this->userModel->findById($userId);
        if ($user === null) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        return $user;
    }
}
