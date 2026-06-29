<?php
declare(strict_types=1);

namespace app\common;

use RuntimeException;

/**
 * 业务异常。
 *
 * Logic 层遇到业务规则失败时抛出该异常，BaseController 会转换为统一错误响应。
 */
final class BusinessException extends RuntimeException
{
    /**
     * @param int $code 业务错误码，来自 ErrorCode。
     * @param string $message 自定义错误信息，留空时使用错误码默认文案。
     */
    public function __construct(int $code, string $message = '')
    {
        parent::__construct($message !== '' ? $message : ErrorCode::message($code), $code);
    }
}
