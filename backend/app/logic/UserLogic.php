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
    private const AVATAR_MAX_SIZE = 5242880;
    private const AVATAR_SIZE = 320;

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

    /**
     * 保存当前用户资料。
     */
    public function saveProfile(int $userId, array $payload): array
    {
        $nickname = trim((string)($payload['nickname'] ?? ''));
        $avatar = trim((string)($payload['avatar'] ?? ''));
        if ($nickname === '') {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '昵称不能为空');
        }
        $nicknameLength = function_exists('mb_strlen') ? mb_strlen($nickname, 'UTF-8') : strlen($nickname);
        if ($nicknameLength > 16) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '昵称最多16个字');
        }
        if ($avatar !== '' && !preg_match('/^(https?:\/\/|\/uploads\/avatars\/)/', $avatar)) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '头像地址格式错误');
        }

        $user = $this->userModel->updateProfile($userId, $nickname, $avatar);
        if ($user === null) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        return $user;
    }

    /**
     * 上传、压缩并保存当前用户头像。
     */
    public function uploadAvatar(int $userId, array $file, ?string $publicRoot = null): array
    {
        $user = $this->userModel->findById($userId);
        if ($user === null) {
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $this->validateAvatarUpload($file);
        $publicRoot = $publicRoot ?? dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public';
        $month = date('Ym');
        $relativeDir = '/uploads/avatars/' . $month;
        $targetDir = rtrim($publicRoot, DIRECTORY_SEPARATOR) . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);
        if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '头像目录创建失败');
        }

        $filename = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.jpg';
        $targetFile = $targetDir . DIRECTORY_SEPARATOR . $filename;
        $relativePath = $relativeDir . '/' . $filename;

        $this->writeNormalizedAvatar((string)$file['tmp_name'], $targetFile);
        $updated = $this->userModel->updateAvatar($userId, $relativePath);
        if ($updated === null) {
            @unlink($targetFile);
            throw new BusinessException(ErrorCode::NOT_FOUND, '用户不存在');
        }

        $this->removeOldLocalAvatar((string)($user['avatar'] ?? ''), $publicRoot);

        return $updated;
    }

    /**
     * 校验上传头像文件。
     */
    private function validateAvatarUpload(array $file): void
    {
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '头像上传失败');
        }
        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '请选择头像图片');
        }
        if ((int)($file['size'] ?? 0) > self::AVATAR_MAX_SIZE) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '头像最大支持5M');
        }

        $mime = $this->detectMime($tmpName);
        $allowedMimes = ['image/jpeg', 'image/png'];
        if (function_exists('imagecreatefromwebp')) {
            $allowedMimes[] = 'image/webp';
        }
        if (!in_array($mime, $allowedMimes, true)) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '头像文件不是有效图片');
        }

        $extension = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        if ($extension !== '' && !in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '头像仅支持jpg、png、webp格式');
        }

        $imageInfo = @getimagesize($tmpName);
        if ($imageInfo === false || empty($imageInfo[0]) || empty($imageInfo[1])) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '头像文件不是有效图片');
        }
    }

    /**
     * 检测服务端真实 MIME。
     */
    private function detectMime(string $path): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                return is_string($mime) ? $mime : '';
            }
        }

        $imageInfo = @getimagesize($path);
        return is_array($imageInfo) ? (string)($imageInfo['mime'] ?? '') : '';
    }

    /**
     * 将头像居中裁剪并写为固定尺寸 JPEG。
     */
    private function writeNormalizedAvatar(string $sourceFile, string $targetFile): void
    {
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '服务器暂不支持图片处理');
        }

        $mime = $this->detectMime($sourceFile);
        $source = $this->createImageResource($sourceFile, $mime);
        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $srcX = (int)(($width - $side) / 2);
        $srcY = (int)(($height - $side) / 2);
        $target = imagecreatetruecolor(self::AVATAR_SIZE, self::AVATAR_SIZE);
        $white = imagecolorallocate($target, 255, 255, 255);
        imagefilledrectangle($target, 0, 0, self::AVATAR_SIZE, self::AVATAR_SIZE, $white);
        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, self::AVATAR_SIZE, self::AVATAR_SIZE, $side, $side);

        $written = imagejpeg($target, $targetFile, 85);
        imagedestroy($source);
        imagedestroy($target);
        if (!$written) {
            throw new BusinessException(ErrorCode::SERVER_ERROR, '头像保存失败');
        }
    }

    /**
     * 根据 MIME 创建 GD 图片资源。
     *
     * @return resource|\GdImage
     */
    private function createImageResource(string $path, string $mime)
    {
        if ($mime === 'image/jpeg' && function_exists('imagecreatefromjpeg')) {
            $image = @imagecreatefromjpeg($path);
        } elseif ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            $image = @imagecreatefrompng($path);
        } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
            $image = @imagecreatefromwebp($path);
        } else {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '头像文件不是有效图片');
        }

        if (!$image) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '头像文件不是有效图片');
        }

        return $image;
    }

    /**
     * 删除旧的本地上传头像，避免误删外部 URL 或越权路径。
     */
    private function removeOldLocalAvatar(string $avatar, string $publicRoot): void
    {
        if (strpos($avatar, '/uploads/avatars/') !== 0) {
            return;
        }

        $publicRoot = rtrim($publicRoot, DIRECTORY_SEPARATOR);
        $avatarRoot = $publicRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
        $path = $publicRoot . str_replace('/', DIRECTORY_SEPARATOR, $avatar);
        $realAvatarRoot = realpath($avatarRoot);
        $realPath = realpath($path);
        if ($realAvatarRoot === false || $realPath === false) {
            return;
        }
        if (strpos($realPath, $realAvatarRoot . DIRECTORY_SEPARATOR) !== 0) {
            return;
        }

        @unlink($realPath);
    }
}
