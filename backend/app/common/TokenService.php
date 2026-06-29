<?php
declare(strict_types=1);

namespace app\common;

/**
 * 业务 token 签发和解析服务。
 *
 * 当前实现用于 MVP 本地开发，token 内容为 userId、签发时间和 HMAC 签名。
 */
final class TokenService
{
    private string $secret;

    /**
     * @param string $secret HMAC 签名密钥，来自环境配置 APP_SECRET。
     */
    public function __construct(string $secret)
    {
        $this->secret = $secret;
    }

    /**
     * 为用户签发业务 token。
     *
     * @param int $userId 用户 ID。
     * @return string 可放入 Authorization Bearer 的 token。
     */
    public function make(int $userId): string
    {
        $payload = $userId . '|' . time();
        return base64_encode($payload . '|' . hash_hmac('sha256', $payload, $this->secret));
    }

    /**
     * 解析并校验业务 token。
     *
     * @param string $token 客户端传入的 Bearer token。
     * @return int token 中的用户 ID。
     */
    public function parse(string $token): int
    {
        $raw = base64_decode($token, true);
        if ($raw === false) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        $parts = explode('|', $raw);
        if (count($parts) !== 3) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        [$userId, $issuedAt, $signature] = $parts;
        $payload = $userId . '|' . $issuedAt;
        $expected = hash_hmac('sha256', $payload, $this->secret);
        if (!hash_equals($expected, $signature)) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        return (int)$userId;
    }
}
