<?php
declare(strict_types=1);

/**
 * 本地开发 API 入口。
 *
 * 该文件模拟 ThinkPHP 路由分发，方便不安装完整框架时用 PHP 内置服务器联调小程序。
 * 启动命令见 README：php -S 127.0.0.1:8010 -t backend/public backend/public/index.php
 */
$root = dirname(__DIR__);
loadEnv($root . DIRECTORY_SEPARATOR . '.env');
putenv('DEV_STORE_PATH=' . $root . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'dev-store.json');

/**
 * 简单 PSR-4 自动加载。
 *
 * 将 app\ 命名空间映射到 backend/app 目录。
 */
spl_autoload_register(function (string $class) use ($root): void {
    $prefix = 'app\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

/**
 * 加载本地 .env 配置到环境变量。
 *
 * @param string $path .env 文件路径。
 */
function loadEnv(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key !== '') {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

use app\common\BaseController;
use app\common\TokenService;
use app\controller\BudgetController;
use app\controller\CategoryController;
use app\controller\HomeController;
use app\controller\RecordController;
use app\controller\UserController;
use app\controller\WxController;

header('Content-Type: application/json; charset=utf-8');

// 读取 HTTP 方法、路由路径和 JSON 请求体。
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$body = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($body)) {
    $body = $_POST;
}

$secret = getenv('APP_SECRET') ?: 'local-dev-secret';
$tokenService = new TokenService($secret);
$base = new BaseController();

// 从 Authorization: Bearer <token> 中解析用户 ID，未登录接口保持 userId=0。
$userId = 0;
$authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (preg_match('/Bearer\s+(.+)/', $authorization, $matches)) {
    try {
        $userId = $tokenService->parse($matches[1]);
    } catch (Throwable $exception) {
        echo json_encode($base->fail($exception), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $response = dispatch($method, $path, $_GET, $body, $userId);
} catch (Throwable $exception) {
    $response = $base->fail($exception);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);

/**
 * 根据 HTTP 方法和路径分发到对应 Controller。
 *
 * @param string $method HTTP 方法。
 * @param string $path 请求路径。
 * @param array $query GET 参数。
 * @param array $body POST JSON 参数。
 * @param int $userId 当前登录用户 ID，未登录为 0。
 * @return array 统一 API 响应。
 */
function dispatch(string $method, string $path, array $query, array $body, int $userId): array
{
    if ($method === 'GET' && $path === '/') {
        return (new BaseController())->success([
            'name' => '萌萌记账 API',
            'status' => 'running',
            'health' => '/api/category/list',
        ]);
    }
    if ($method === 'POST' && $path === '/api/wx/login') {
        return controller(new WxController(), $query, $body, $userId)->login();
    }
    if ($method === 'GET' && $path === '/api/user/profile') {
        return controller(new UserController(), $query, $body, $userId)->profile();
    }
    if ($method === 'GET' && $path === '/api/category/list') {
        return controller(new CategoryController(), $query, $body, $userId)->list();
    }
    if ($method === 'POST' && $path === '/api/record/create') {
        return controller(new RecordController(), $query, $body, $userId)->create();
    }
    if ($method === 'POST' && $path === '/api/record/update') {
        return controller(new RecordController(), $query, $body, $userId)->update();
    }
    if ($method === 'POST' && $path === '/api/record/delete') {
        return controller(new RecordController(), $query, $body, $userId)->delete();
    }
    if ($method === 'GET' && $path === '/api/record/list') {
        return controller(new RecordController(), $query, $body, $userId)->list();
    }
    if ($method === 'GET' && $path === '/api/record/detail') {
        return controller(new RecordController(), $query, $body, $userId)->detail();
    }
    if ($method === 'GET' && $path === '/api/home/summary') {
        return controller(new HomeController(), $query, $body, $userId)->summary();
    }
    if ($method === 'GET' && $path === '/api/budget/month') {
        return controller(new BudgetController(), $query, $body, $userId)->month();
    }
    if ($method === 'POST' && $path === '/api/budget/save') {
        return controller(new BudgetController(), $query, $body, $userId)->save();
    }

    return $base = (new BaseController())->fail(new \app\common\BusinessException(\app\common\ErrorCode::NOT_FOUND, '接口不存在'));
}

/**
 * 为 Controller 注入请求上下文。
 *
 * @template T of BaseController
 * @param T $controller 业务 Controller。
 * @param array $query GET 参数。
 * @param array $body POST body 参数。
 * @param int $userId 当前登录用户 ID。
 * @return T 已注入上下文的 Controller。
 */
function controller(BaseController $controller, array $query, array $body, int $userId): BaseController
{
    $controller->setRequestContext($query, $body, $userId);
    return $controller;
}
