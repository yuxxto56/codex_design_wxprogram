<?php
declare(strict_types=1);

$root = dirname(__DIR__);

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

final class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;

    public function test(string $name, callable $case): void
    {
        try {
            $case();
            $this->passed++;
            echo "[PASS] {$name}\n";
        } catch (Throwable $exception) {
            $this->failed++;
            echo "[FAIL] {$name}: {$exception->getMessage()}\n";
        }
    }

    public function assertSame($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $detail = $message !== '' ? $message . ' ' : '';
            throw new RuntimeException($detail . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
        }
    }

    public function assertTrue(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    }

    public function assertArrayHasKey(string $key, array $actual): void
    {
        if (!array_key_exists($key, $actual)) {
            throw new RuntimeException("missing key {$key}");
        }
    }

    public function finish(): void
    {
        echo "\n{$this->passed} passed, {$this->failed} failed\n";
        if ($this->failed > 0) {
            exit(1);
        }
    }
}

$runner = new TestRunner();

require __DIR__ . '/unit/ResponseShapeTest.php';
require __DIR__ . '/unit/WxAuthLogicTest.php';
require __DIR__ . '/unit/UserModelTest.php';
require __DIR__ . '/unit/LedgerLogicTest.php';
require __DIR__ . '/unit/LayeringRuleTest.php';
require __DIR__ . '/unit/DatabaseScriptTest.php';

$runner->finish();
