<?php
declare(strict_types=1);

namespace app\common;

/**
 * 金额转换工具。
 *
 * 前端用元展示和传参，后端统一用整数分存储，避免浮点数精度问题。
 */
final class Money
{
    /**
     * 将元格式金额转换为分。
     *
     * @param string $amount 元格式金额，例如 12.30。
     * @return int 分格式金额，例如 1230。
     */
    public static function yuanToCent(string $amount): int
    {
        $amount = trim($amount);
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '金额格式错误');
        }

        [$yuan, $cent] = array_pad(explode('.', $amount, 2), 2, '0');
        return ((int)$yuan * 100) + (int)str_pad(substr($cent, 0, 2), 2, '0');
    }

    /**
     * 将分格式金额转换为元格式字符串。
     *
     * @param int $amount 分格式金额。
     * @return string 元格式金额，固定两位小数。
     */
    public static function centToYuan(int $amount): string
    {
        return number_format($amount / 100, 2, '.', '');
    }
}
