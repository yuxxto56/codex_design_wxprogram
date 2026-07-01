<?php
declare(strict_types=1);

use app\common\BaseController;
use app\controller\BudgetController;
use app\controller\CategoryController;
use app\controller\HomeController;
use app\controller\RecordController;
use app\controller\UserController;
use app\controller\WxController;

$runner->test('business controllers inherit base controller', function () use ($runner): void {
    foreach ([BudgetController::class, CategoryController::class, HomeController::class, RecordController::class, UserController::class, WxController::class] as $class) {
        $runner->assertTrue(is_subclass_of($class, BaseController::class), "{$class} should inherit BaseController");
    }
});

$runner->test('business controller actions do not receive request context as method parameters', function () use ($runner): void {
    foreach ([BudgetController::class, CategoryController::class, HomeController::class, RecordController::class, UserController::class, WxController::class] as $class) {
        $reflection = new ReflectionClass($class);
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            foreach ($method->getParameters() as $parameter) {
                $type = $parameter->getType();
                $typeName = $type instanceof ReflectionNamedType ? $type->getName() : '';
                $name = $parameter->getName();

                $runner->assertTrue(
                    !($typeName === 'array' && in_array($name, ['body', 'query', 'request'], true)),
                    "{$class}::{$method->getName()} must not receive {$name} array; read parameters one by one through BaseController"
                );
                $runner->assertTrue(
                    !($typeName === 'int' && $name === 'userId'),
                    "{$class}::{$method->getName()} must not receive userId as method parameter; use currentUserId()"
                );
            }
        }
    }
});

$runner->test('business backend code does not use local file storage fallback', function () use ($runner): void {
    $root = dirname(__DIR__, 2);
    foreach ([$root . '/app', $root . '/public'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $content = file_get_contents($file->getPathname()) ?: '';
            foreach (['JsonStore', 'DEV_STORE_PATH', 'dev-store'] as $forbidden) {
                $runner->assertTrue(
                    strpos($content, $forbidden) === false,
                    $file->getPathname() . " must not reference {$forbidden}; business data should use MySQL"
                );
            }
        }
    }
});

$runner->test('local api entry configures php timezone to Asia Shanghai', function () use ($runner): void {
    $entry = dirname(__DIR__, 2) . '/public/index.php';
    $content = file_get_contents($entry) ?: '';

    $runner->assertTrue(
        strpos($content, "date_default_timezone_set('Asia/Shanghai')") !== false
            || strpos($content, 'date_default_timezone_set("Asia/Shanghai")') !== false,
        'backend public entry should configure PHP timezone to Asia/Shanghai'
    );
});

$runner->test('miniprogram code avoids Array flatMap for base library compatibility', function () use ($runner): void {
    $directory = dirname(__DIR__, 3) . '/miniprogram';
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    foreach ($iterator as $file) {
        if (!$file->isFile() || !in_array($file->getExtension(), ['ts', 'js'], true)) {
            continue;
        }

        $content = file_get_contents($file->getPathname()) ?: '';
        $runner->assertTrue(
            strpos($content, '.flatMap(') === false,
            $file->getPathname() . ' must not use Array.flatMap; some WeChat base libraries cannot render after this runtime error'
        );
    }
});

$runner->test('bill page renders current records independently from helper requests', function () use ($runner): void {
    $billPage = dirname(__DIR__, 3) . '/miniprogram/pages/bill/bill.ts';
    $content = file_get_contents($billPage) ?: '';

    $runner->assertTrue(
        strpos($content, 'Promise.all') === false,
        'bill page should not gate current record rendering behind Promise.all helper requests'
    );
});

$runner->test('bill page labels the current selected month without auto switching to record month', function () use ($runner): void {
    $billPage = dirname(__DIR__, 3) . '/miniprogram/pages/bill/bill.ts';
    $billTemplate = dirname(__DIR__, 3) . '/miniprogram/pages/bill/bill.wxml';
    $content = file_get_contents($billPage) ?: '';
    $templateContent = file_get_contents($billTemplate) ?: '';

    $runner->assertTrue(
        strpos($content, 'recordLatestMonth') === false
            && strpos($content, 'monthNote') !== false
            && strpos($content, '当前月份：') !== false
            && strpos($templateContent, 'monthNote') !== false,
        'bill page should label the current selected month and should not auto switch to the latest record month'
    );
});

$runner->test('home page labels current month without falling back recent records', function () use ($runner): void {
    $homePage = dirname(__DIR__, 3) . '/miniprogram/pages/home/home.ts';
    $content = file_get_contents($homePage) ?: '';

    $runner->assertTrue(
        strpos($content, 'recentMonthLabel') !== false
            && strpos($content, 'formatMonthLabel') !== false
            && strpos($content, 'loadFallbackRecentRecords') === false,
        'home page should label the current month and should not fall back recent records to previous month'
    );
});

$runner->test('home page labels the month used by recent records', function () use ($runner): void {
    $homeScript = dirname(__DIR__, 3) . '/miniprogram/pages/home/home.ts';
    $homeTemplate = dirname(__DIR__, 3) . '/miniprogram/pages/home/home.wxml';
    $scriptContent = file_get_contents($homeScript) ?: '';
    $templateContent = file_get_contents($homeTemplate) ?: '';

    $runner->assertTrue(
        strpos($scriptContent, 'recentMonthLabel') !== false
            && strpos($templateContent, 'month-badge') !== false
            && strpos($templateContent, '{{recentMonthLabel}}还没有记录') !== false,
        'home page should visibly label which month the recent-record list belongs to'
    );
});
