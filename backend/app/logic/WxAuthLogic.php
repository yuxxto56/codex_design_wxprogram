<?php
declare(strict_types=1);

namespace app\logic;

use app\common\BusinessException;
use app\common\ErrorCode;
use app\model\UserModel;

/**
 * 微信登录业务逻辑。
 *
 * 支持 mock_ 开头的开发 code；真实微信 code 会通过微信 code2Session 解析 openid。
 */
final class WxAuthLogic
{
    private UserModel $userModel;
    private \app\common\TokenService $tokenService;
    private string $wechatAppid;
    private string $wechatSecret;
    /** @var callable */
    private $httpClient;

    /**
     * @param UserModel|null $userModel 用户数据访问模型。
     * @param string|null $tokenSecret token 签名密钥，默认读取 APP_SECRET。
     * @param string|null $wechatAppid 微信小程序 appid，默认读取 WECHAT_APPID。
     * @param string|null $wechatSecret 微信小程序 secret，默认读取 WECHAT_SECRET。
     * @param callable|null $httpClient HTTP GET 客户端，测试时可注入。
     */
    public function __construct(
        ?UserModel $userModel = null,
        ?string $tokenSecret = null,
        ?string $wechatAppid = null,
        ?string $wechatSecret = null,
        ?callable $httpClient = null
    ) {
        $this->userModel = $userModel ?? new UserModel();
        $this->tokenService = new \app\common\TokenService($tokenSecret ?? (getenv('APP_SECRET') ?: 'local-dev-secret'));
        $this->wechatAppid = trim($wechatAppid ?? (getenv('WECHAT_APPID') ?: ''));
        $this->wechatSecret = trim($wechatSecret ?? (getenv('WECHAT_SECRET') ?: ''));
        $this->httpClient = $httpClient ?? [$this, 'httpGet'];
    }

    /**
     * 使用小程序登录 code 完成登录。
     *
     * @param string $code wx.login 返回的 code，开发环境可传 mock_xxx。
     * @return array{token:string,user:array}
     */
    public function loginByCode(string $code): array
    {
        if ($code === '') {
            throw new BusinessException(ErrorCode::PARAM_INVALID, 'code不能为空');
        }

        $openid = $this->resolveOpenid($code);
        $user = $this->userModel->findOrCreateByOpenid($openid);

        return [
            'token' => $this->tokenService->make((int)$user['id']),
            'user' => $user,
        ];
    }

    /**
     * 将登录 code 解析为 openid。
     *
     * mock_ code 用于本地开发；真实 code 通过微信 jscode2session 换取 openid。
     *
     * @param string $code 小程序登录 code。
     * @return string openid。
     */
    private function resolveOpenid(string $code): string
    {
        if (strpos($code, 'mock_') === 0) {
            return 'openid_' . substr($code, 5);
        }

        if ($this->wechatAppid === '' || $this->wechatSecret === '' || $this->wechatAppid === 'your-wechat-mini-program-appid' || $this->wechatSecret === 'your-wechat-mini-program-secret') {
            throw new BusinessException(ErrorCode::WX_LOGIN_FAILED, '请配置 WECHAT_APPID 和 WECHAT_SECRET 后再使用微信一键登录');
        }

        $url = 'https://api.weixin.qq.com/sns/jscode2session'
            . '?appid=' . rawurlencode($this->wechatAppid)
            . '&secret=' . rawurlencode($this->wechatSecret)
            . '&js_code=' . rawurlencode($code)
            . '&grant_type=authorization_code';
        $raw = (string)call_user_func($this->httpClient, $url);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new BusinessException(ErrorCode::WX_LOGIN_FAILED, '微信登录接口返回格式异常');
        }
        if (isset($data['errcode']) && (int)$data['errcode'] !== 0) {
            throw new BusinessException(ErrorCode::WX_LOGIN_FAILED, $this->wechatErrorMessage((int)$data['errcode'], (string)($data['errmsg'] ?? '')));
        }
        if (empty($data['openid'])) {
            throw new BusinessException(ErrorCode::WX_LOGIN_FAILED, '微信登录失败：未获取到 openid');
        }

        return (string)$data['openid'];
    }

    /**
     * 将微信 code2Session 错误转换为可操作的中文提示。
     *
     * @param int $errcode 微信错误码。
     * @param string $errmsg 微信原始错误信息。
     * @return string 面向开发调试的错误提示。
     */
    private function wechatErrorMessage(int $errcode, string $errmsg): string
    {
        if ($errcode === 40029) {
            return '微信登录失败：code 无效或已过期，请重新点击微信登录；如果仍失败，请确认微信开发者工具项目 AppID 与后端 WECHAT_APPID/WECHAT_SECRET 属于同一个小程序';
        }
        if ($errcode === 40125) {
            return '微信登录失败：AppSecret 无效，请在微信公众平台复制当前小程序的 AppSecret 到 WECHAT_SECRET';
        }

        return '微信登录失败：' . ($errmsg !== '' ? $errmsg : 'code2Session 调用失败');
    }

    /**
     * 请求远程 HTTP GET 接口。
     *
     * @param string $url 请求地址。
     * @return string 响应文本。
     */
    private function httpGet(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new BusinessException(ErrorCode::WX_LOGIN_FAILED, '微信登录接口请求失败，请检查服务器网络和微信配置');
        }

        return $response;
    }
}
