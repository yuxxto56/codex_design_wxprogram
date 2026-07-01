<?php
declare(strict_types=1);

use app\common\BaseController;
use app\common\BusinessException;
use app\controller\CategoryController;
use app\controller\RecordController;
use app\controller\UserController;
use app\controller\WxController;

$runner->test('success response uses unified shape', function () use ($runner): void {
    $controller = new BaseController();

    $response = $controller->success(['id' => 1]);

    $runner->assertSame(0, $response['code']);
    $runner->assertSame('success', $response['message']);
    $runner->assertSame(['id' => 1], $response['data']);
});

$runner->test('business error response keeps data as empty array', function () use ($runner): void {
    $controller = new BaseController();

    $response = $controller->fail(new BusinessException(1001));

    $runner->assertSame(1001, $response['code']);
    $runner->assertSame([], $response['data']);
    $runner->assertTrue($response['message'] !== '', 'error message should not be empty');
});

$runner->test('controller missing required post parameter returns unified error shape', function () use ($runner): void {
    $controller = new WxController();
    $controller->setRequestContext([], [], 0);

    $response = $controller->login();

    $runner->assertTrue($response['code'] !== 0, 'missing code should fail');
    $runner->assertSame([], $response['data']);
});

$runner->test('base controller reads fallback get and post parameters one by one', function () use ($runner): void {
    $controller = new class extends BaseController {
        public function readGetString(string $key): string
        {
            return $this->getRequiredString($key);
        }

        public function readPostString(string $key): string
        {
            return $this->postRequiredString($key);
        }

        public function readPostInt(string $key): int
        {
            return $this->postInt($key);
        }
    };
    $controller->setRequestContext(['month' => '2026-06'], ['amount' => '12.30', 'type' => '1'], 9);

    $runner->assertSame('2026-06', $controller->readGetString('month'));
    $runner->assertSame('12.30', $controller->readPostString('amount'));
    $runner->assertSame(1, $controller->readPostInt('type'));
});

$runner->test('category list requires login', function () use ($runner): void {
    $controller = new CategoryController();
    $controller->setRequestContext([], [], 0);

    $response = $controller->list();

    $runner->assertSame(1002, $response['code']);
    $runner->assertSame([], $response['data']);
});

$runner->test('category create requires login', function () use ($runner): void {
    $controller = new CategoryController();
    $controller->setRequestContext([], ['type' => 1, 'name' => '咖啡'], 0);

    $response = $controller->create();

    $runner->assertSame(1002, $response['code']);
    $runner->assertSame([], $response['data']);
});

$runner->test('avatar upload requires login', function () use ($runner): void {
    $controller = new UserController();
    $controller->setRequestContext([], [], 0);
    $controller->setUploadedFiles([]);

    $response = $controller->uploadAvatar();

    $runner->assertSame(1002, $response['code']);
    $runner->assertSame([], $response['data']);
});

$runner->test('record latest month requires login', function () use ($runner): void {
    $controller = new RecordController();
    $controller->setRequestContext([], [], 0);

    $response = $controller->latestMonth();

    $runner->assertSame(1002, $response['code']);
    $runner->assertSame([], $response['data']);
});
