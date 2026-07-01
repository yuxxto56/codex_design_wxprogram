<?php
declare(strict_types=1);

use app\model\UserModel;
use app\logic\UserLogic;
use app\common\BusinessException;
use app\common\ErrorCode;

$runner->test('user model persists login user through database storage', function () use ($runner): void {
    $pdo = new FakePdo();
    $loginModel = new UserModel($pdo);

    $created = $loginModel->findOrCreateByOpenid('openid_profile_user');

    $profileModel = new UserModel($pdo);
    $found = $profileModel->findById((int)$created['id']);

    $runner->assertSame('openid_profile_user', $found['openid'] ?? null);
});

$runner->test('user model fails clearly when database storage is unavailable', function () use ($runner): void {
    $previousHost = getenv('DATABASE_HOST');
    $previousName = getenv('DATABASE_NAME');
    $previousUser = getenv('DATABASE_USER');
    putenv('DATABASE_HOST');
    putenv('DATABASE_NAME');
    putenv('DATABASE_USER');

    try {
        (new UserModel())->findOrCreateByOpenid('openid_without_storage');
        throw new RuntimeException('expected user model to require database storage');
    } catch (BusinessException $exception) {
        $runner->assertSame(ErrorCode::SERVER_ERROR, $exception->getCode());
        $runner->assertSame('数据库连接失败，请检查数据库配置', $exception->getMessage());
    } finally {
        if ($previousHost === false) {
            putenv('DATABASE_HOST');
        } else {
            putenv('DATABASE_HOST=' . $previousHost);
        }
        if ($previousName === false) {
            putenv('DATABASE_NAME');
        } else {
            putenv('DATABASE_NAME=' . $previousName);
        }
        if ($previousUser === false) {
            putenv('DATABASE_USER');
        } else {
            putenv('DATABASE_USER=' . $previousUser);
        }
    }
});

$runner->test('user logic saves nickname and avatar profile', function () use ($runner): void {
    $pdo = new FakePdo();
    $model = new UserModel($pdo);
    $created = $model->findOrCreateByOpenid('openid_save_profile');
    $logic = new UserLogic($model);

    $saved = $logic->saveProfile((int)$created['id'], [
        'nickname' => '小月',
        'avatar' => 'https://example.com/avatar.png',
    ]);
    $found = $model->findById((int)$created['id']);

    $runner->assertSame('小月', $saved['nickname']);
    $runner->assertSame('https://example.com/avatar.png', $saved['avatar']);
    $runner->assertSame('小月', $found['nickname']);
});

$runner->test('user avatar upload rejects files larger than five megabytes', function () use ($runner): void {
    $pdo = new FakePdo();
    $model = new UserModel($pdo);
    $created = $model->findOrCreateByOpenid('openid_large_avatar');
    $logic = new UserLogic($model);

    try {
        $logic->uploadAvatar((int)$created['id'], [
            'error' => UPLOAD_ERR_OK,
            'name' => 'large.png',
            'type' => 'image/png',
            'tmp_name' => __FILE__,
            'size' => 5 * 1024 * 1024 + 1,
        ], sys_get_temp_dir());
        throw new RuntimeException('expected large avatar to be rejected');
    } catch (BusinessException $exception) {
        $runner->assertSame(ErrorCode::PARAM_INVALID, $exception->getCode());
        $runner->assertSame('头像最大支持5M', $exception->getMessage());
    }
});

$runner->test('user avatar upload rejects non image files', function () use ($runner): void {
    $pdo = new FakePdo();
    $model = new UserModel($pdo);
    $created = $model->findOrCreateByOpenid('openid_fake_avatar');
    $logic = new UserLogic($model);
    $tempFile = tempnam(sys_get_temp_dir(), 'avatar_fake_');
    if ($tempFile === false) {
        throw new RuntimeException('failed to create temp file');
    }
    file_put_contents($tempFile, 'not an image');

    try {
        $logic->uploadAvatar((int)$created['id'], [
            'error' => UPLOAD_ERR_OK,
            'name' => 'fake.png',
            'type' => 'image/png',
            'tmp_name' => $tempFile,
            'size' => filesize($tempFile),
        ], sys_get_temp_dir());
        throw new RuntimeException('expected fake avatar to be rejected');
    } catch (BusinessException $exception) {
        $runner->assertSame(ErrorCode::PARAM_INVALID, $exception->getCode());
        $runner->assertSame('头像文件不是有效图片', $exception->getMessage());
    } finally {
        @unlink($tempFile);
    }
});

$runner->test('user avatar upload stores normalized jpg and updates profile', function () use ($runner): void {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) {
        return;
    }
    $pdo = new FakePdo();
    $model = new UserModel($pdo);
    $created = $model->findOrCreateByOpenid('openid_upload_avatar');
    $logic = new UserLogic($model);
    $storageRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avatar_upload_' . bin2hex(random_bytes(4));
    $sourceFile = tempnam(sys_get_temp_dir(), 'avatar_src_');
    if ($sourceFile === false) {
        throw new RuntimeException('failed to create source file');
    }
    $image = imagecreatetruecolor(40, 20);
    imagefilledrectangle($image, 0, 0, 39, 19, imagecolorallocate($image, 255, 180, 190));
    imagepng($image, $sourceFile);
    imagedestroy($image);

    try {
        $saved = $logic->uploadAvatar((int)$created['id'], [
            'error' => UPLOAD_ERR_OK,
            'name' => 'avatar.png',
            'type' => 'image/png',
            'tmp_name' => $sourceFile,
            'size' => filesize($sourceFile),
        ], $storageRoot);

        $runner->assertTrue(strpos($saved['avatar'], '/uploads/avatars/') === 0, 'avatar path should use public uploads path');
        $runner->assertTrue(substr($saved['avatar'], -4) === '.jpg', 'avatar should be normalized to jpg');
        $runner->assertSame($saved['avatar'], $model->findById((int)$created['id'])['avatar'] ?? '');
        $storedFile = $storageRoot . str_replace('/', DIRECTORY_SEPARATOR, $saved['avatar']);
        $runner->assertTrue(is_file($storedFile), 'normalized avatar file should exist');
        $dimensions = getimagesize($storedFile);
        $runner->assertSame(320, $dimensions[0] ?? 0);
        $runner->assertSame(320, $dimensions[1] ?? 0);
    } finally {
        @unlink($sourceFile);
        if (is_dir($storageRoot)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($storageRoot);
        }
    }
});

$runner->test('user avatar upload accepts valid image without original extension', function () use ($runner): void {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return;
    }
    $pdo = new FakePdo();
    $model = new UserModel($pdo);
    $created = $model->findOrCreateByOpenid('openid_upload_avatar_no_ext');
    $logic = new UserLogic($model);
    $storageRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avatar_no_ext_' . bin2hex(random_bytes(4));
    $sourceFile = tempnam(sys_get_temp_dir(), 'avatar_src_');
    if ($sourceFile === false) {
        throw new RuntimeException('failed to create source file');
    }
    $image = imagecreatetruecolor(24, 24);
    imagefilledrectangle($image, 0, 0, 23, 23, imagecolorallocate($image, 210, 180, 200));
    imagejpeg($image, $sourceFile);
    imagedestroy($image);

    try {
        $saved = $logic->uploadAvatar((int)$created['id'], [
            'error' => UPLOAD_ERR_OK,
            'name' => 'wxfile',
            'type' => 'application/octet-stream',
            'tmp_name' => $sourceFile,
            'size' => filesize($sourceFile),
        ], $storageRoot);

        $runner->assertTrue(strpos($saved['avatar'], '/uploads/avatars/') === 0, 'avatar path should use public uploads path');
    } finally {
        @unlink($sourceFile);
        if (is_dir($storageRoot)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($storageRoot);
        }
    }
});

$runner->test('user avatar upload removes previous local avatar safely', function () use ($runner): void {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagejpeg')) {
        return;
    }
    $pdo = new FakePdo();
    $model = new UserModel($pdo);
    $created = $model->findOrCreateByOpenid('openid_replace_avatar');
    $logic = new UserLogic($model);
    $storageRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'avatar_replace_' . bin2hex(random_bytes(4));
    $oldRelative = '/uploads/avatars/202606/avatar_1_old.jpg';
    $oldFile = $storageRoot . str_replace('/', DIRECTORY_SEPARATOR, $oldRelative);
    if (!is_dir(dirname($oldFile)) && !mkdir(dirname($oldFile), 0777, true) && !is_dir(dirname($oldFile))) {
        throw new RuntimeException('failed to create old avatar directory');
    }
    file_put_contents($oldFile, 'old');
    $model->updateProfile((int)$created['id'], (string)$created['nickname'], $oldRelative);
    $sourceFile = tempnam(sys_get_temp_dir(), 'avatar_src_');
    if ($sourceFile === false) {
        throw new RuntimeException('failed to create source file');
    }
    $image = imagecreatetruecolor(20, 20);
    imagefilledrectangle($image, 0, 0, 19, 19, imagecolorallocate($image, 200, 220, 210));
    imagejpeg($image, $sourceFile);
    imagedestroy($image);

    try {
        $logic->uploadAvatar((int)$created['id'], [
            'error' => UPLOAD_ERR_OK,
            'name' => 'avatar.jpg',
            'type' => 'image/jpeg',
            'tmp_name' => $sourceFile,
            'size' => filesize($sourceFile),
        ], $storageRoot);

        $runner->assertTrue(!is_file($oldFile), 'old local avatar should be removed');
    } finally {
        @unlink($sourceFile);
        if (is_dir($storageRoot)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
            }
            @rmdir($storageRoot);
        }
    }
});
