<?php
declare(strict_types=1);

namespace app\common;

use think\facade\Request;
use Throwable;

/**
 * API 控制器基类。
 *
 * 统一封装成功/失败响应、常用取参校验和登录校验，业务 Controller 只负责
 * 接收参数、调用 Logic、返回本类提供的统一响应结构。
 */
class BaseController
{
    protected array $query = [];
    protected array $body = [];
    protected array $files = [];
    protected int $userId = 0;

    /**
     * 注入当前请求上下文。
     *
     * 本地开发入口或框架适配层只负责注入 query/body/userId；业务 Controller 方法
     * 不直接接收整包请求参数，而是在方法内部通过本类逐个读取字段。
     *
     * @param array $query GET query 参数。
     * @param array $body POST body 参数。
     * @param int $userId 当前登录用户 ID，未登录为 0。
     */
    public function setRequestContext(array $query, array $body, int $userId): void
    {
        $this->query = $query;
        $this->body = $body;
        $this->userId = $userId;
    }

    /**
     * 注入上传文件上下文。
     *
     * 本地入口和测试可注入 $_FILES 结构，业务 Controller 通过 uploadedFile() 逐个读取。
     *
     * @param array $files 上传文件数组。
     */
    public function setUploadedFiles(array $files): void
    {
        $this->files = $files;
    }

    /**
     * 返回统一成功响应。
     *
     * @param array $data 业务数据，成功时必须返回真实业务结构。
     * @return array{code:int,message:string,data:array}
     */
    public function success(array $data = []): array
    {
        return [
            'code' => ErrorCode::SUCCESS,
            'message' => ErrorCode::message(ErrorCode::SUCCESS),
            'data' => $data,
        ];
    }

    /**
     * 返回统一失败响应。
     *
     * 业务异常使用异常自身 code；未知异常统一映射为系统错误，并保证 data 为空数组。
     *
     * @param Throwable $exception 业务异常或系统异常。
     * @return array{code:int,message:string,data:array}
     */
    public function fail(Throwable $exception): array
    {
        $code = $exception instanceof BusinessException ? $exception->getCode() : ErrorCode::SERVER_ERROR;

        return [
            'code' => $code,
            'message' => $exception->getMessage() !== '' ? $exception->getMessage() : ErrorCode::message($code),
            'data' => [],
        ];
    }

    /**
     * 读取必填字符串参数。
     *
     * @param array $source 请求参数来源，通常为 query 或 body。
     * @param string $key 参数名。
     * @return string 去除首尾空格后的参数值。
     */
    protected function requireString(array $source, string $key): string
    {
        $value = trim((string)($source[$key] ?? ''));
        if ($value === '') {
            throw new BusinessException(ErrorCode::PARAM_INVALID, "{$key}不能为空");
        }

        return $value;
    }

    /**
     * 从 GET query 中读取必填字符串参数。
     *
     * 正式 TP6 环境优先使用 Request::get()；本地/测试环境回退到注入的 query。
     *
     * @param string $key 参数名。
     */
    protected function getRequiredString(string $key): string
    {
        return $this->requiredValue($this->getValue($key), $key);
    }

    /**
     * 从 POST body 中读取必填字符串参数。
     *
     * 正式 TP6 环境优先使用 Request::post()；本地/测试环境回退到注入的 body。
     *
     * @param string $key 参数名。
     */
    protected function postRequiredString(string $key): string
    {
        return $this->requiredValue($this->postValue($key), $key);
    }

    /**
     * 从 GET query 中读取可选字符串参数。
     *
     * @param string $key 参数名。
     * @param string $default 参数不存在时的默认值。
     */
    protected function getOptionalString(string $key, string $default = ''): string
    {
        return trim((string)$this->getValue($key, $default));
    }

    /**
     * 从 POST body 中读取可选字符串参数。
     *
     * @param string $key 参数名。
     * @param string $default 参数不存在时的默认值。
     */
    protected function postOptionalString(string $key, string $default = ''): string
    {
        return trim((string)$this->postValue($key, $default));
    }

    /**
     * 读取可选字符串参数。
     *
     * @param array $source 请求参数来源。
     * @param string $key 参数名。
     * @param string $default 参数不存在时的默认值。
     */
    protected function optionalString(array $source, string $key, string $default = ''): string
    {
        return trim((string)($source[$key] ?? $default));
    }

    /**
     * 读取整数参数。
     *
     * @param array $source 请求参数来源。
     * @param string $key 参数名。
     * @param int $default 参数不存在时的默认值。
     */
    protected function intParam(array $source, string $key, int $default = 0): int
    {
        return (int)($source[$key] ?? $default);
    }

    /**
     * 从 GET query 中读取整数参数。
     *
     * @param string $key 参数名。
     * @param int $default 参数不存在时的默认值。
     */
    protected function getInt(string $key, int $default = 0): int
    {
        return (int)$this->getValue($key, $default);
    }

    /**
     * 从 POST body 中读取整数参数。
     *
     * @param string $key 参数名。
     * @param int $default 参数不存在时的默认值。
     */
    protected function postInt(string $key, int $default = 0): int
    {
        return (int)$this->postValue($key, $default);
    }

    /**
     * 读取上传文件。
     *
     * @param string $key 文件字段名。
     * @return array 上传文件结构。
     */
    protected function uploadedFile(string $key): array
    {
        if (class_exists(Request::class)) {
            try {
                $file = Request::file($key);
                if (is_array($file)) {
                    return $file;
                }
                if (is_object($file)) {
                    return $this->normalizeUploadedFileObject($file);
                }
            } catch (Throwable $exception) {
                // 本地入口没有 TP6 请求上下文时回退到注入的 $_FILES。
            }
        }

        $file = $this->files[$key] ?? ($_FILES[$key] ?? null);
        if (!is_array($file)) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '请选择头像图片');
        }

        return $file;
    }

    /**
     * 兼容 TP6 上传文件对象，转换为项目统一的上传文件数组。
     *
     * @param object $file 上传文件对象。
     * @return array 上传文件结构。
     */
    private function normalizeUploadedFileObject(object $file): array
    {
        $path = method_exists($file, 'getPathname') ? (string)$file->getPathname() : '';
        $name = method_exists($file, 'getOriginalName') ? (string)$file->getOriginalName() : 'avatar';
        $size = method_exists($file, 'getSize') ? (int)$file->getSize() : ($path !== '' && is_file($path) ? (int)filesize($path) : 0);
        $mime = method_exists($file, 'getMime') ? (string)$file->getMime() : '';

        return [
            'error' => UPLOAD_ERR_OK,
            'name' => $name,
            'type' => $mime,
            'tmp_name' => $path,
            'size' => $size,
        ];
    }

    /**
     * 获取并校验当前登录用户 ID。
     *
     * @return int 合法用户 ID。
     */
    protected function currentUserId(): int
    {
        return $this->requireLogin($this->userId);
    }

    /**
     * 校验当前接口必须登录。
     *
     * @param int|null $userId token 解析出的用户 ID。
     * @return int 合法用户 ID。
     */
    protected function requireLogin(?int $userId): int
    {
        if ($userId === null || $userId <= 0) {
            throw new BusinessException(ErrorCode::UNAUTHORIZED);
        }

        return $userId;
    }

    /**
     * 获取 GET 参数值，优先使用 TP6 Request facade。
     *
     * @param string $key 参数名。
     * @param mixed $default 默认值。
     * @return mixed
     */
    private function getValue(string $key, $default = '')
    {
        if (class_exists(Request::class)) {
            return Request::get($key, $default);
        }

        return $this->query[$key] ?? $default;
    }

    /**
     * 获取 POST 参数值，优先使用 TP6 Request facade。
     *
     * @param string $key 参数名。
     * @param mixed $default 默认值。
     * @return mixed
     */
    private function postValue(string $key, $default = '')
    {
        if (class_exists(Request::class)) {
            return Request::post($key, $default);
        }

        return $this->body[$key] ?? $default;
    }

    /**
     * 校验必填值并转换为去空格字符串。
     *
     * @param mixed $value 参数值。
     * @param string $key 参数名。
     */
    private function requiredValue($value, string $key): string
    {
        $value = trim((string)$value);
        if ($value === '') {
            throw new BusinessException(ErrorCode::PARAM_INVALID, "{$key}不能为空");
        }

        return $value;
    }
}
