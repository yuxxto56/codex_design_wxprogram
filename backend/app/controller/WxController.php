<?php
declare(strict_types=1);

namespace app\controller;

use app\common\BaseController;
use app\logic\WxAuthLogic;
use Throwable;

/**
 * 微信登录 API 控制器。
 *
 * 接收小程序 wx.login 返回的 code，调用 WxAuthLogic 完成登录并签发业务 token。
 */
final class WxController extends BaseController
{
    private WxAuthLogic $logic;

    public function __construct(?WxAuthLogic $logic = null)
    {
        $this->logic = $logic ?? new WxAuthLogic();
    }

    /**
     * 微信 code 登录。
     *
     * 从 POST body 逐个读取 code。
     */
    public function login(): array
    {
        try {
            return $this->success($this->logic->loginByCode($this->postRequiredString('code')));
        } catch (Throwable $exception) {
            return $this->fail($exception);
        }
    }
}
