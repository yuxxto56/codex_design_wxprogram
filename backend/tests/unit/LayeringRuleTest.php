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
