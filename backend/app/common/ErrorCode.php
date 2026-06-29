<?php
declare(strict_types=1);

namespace app\common;

/**
 * API 错误码和默认错误文案映射。
 *
 * 所有业务错误码集中定义，避免 Controller/Logic 中散落魔法数字。
 */
final class ErrorCode
{
    public const SUCCESS = 0;
    public const PARAM_INVALID = 1001;
    public const UNAUTHORIZED = 1002;
    public const NOT_FOUND = 1003;
    public const WX_LOGIN_FAILED = 1004;
    public const RECORD_FORBIDDEN = 1005;
    public const SERVER_ERROR = 9999;

    private const MESSAGES = [
        self::SUCCESS => 'success',
        self::PARAM_INVALID => '参数错误',
        self::UNAUTHORIZED => '请先登录',
        self::NOT_FOUND => '数据不存在',
        self::WX_LOGIN_FAILED => '微信登录失败',
        self::RECORD_FORBIDDEN => '无权操作该记录',
        self::SERVER_ERROR => '系统繁忙，请稍后再试',
    ];

    /**
     * 根据错误码获取默认错误文案。
     *
     * @param int $code 业务错误码。
     * @return string 错误码对应文案，未知错误码返回系统错误文案。
     */
    public static function message(int $code): string
    {
        return self::MESSAGES[$code] ?? self::MESSAGES[self::SERVER_ERROR];
    }
}
