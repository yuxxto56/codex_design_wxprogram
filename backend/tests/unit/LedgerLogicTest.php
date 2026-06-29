<?php
declare(strict_types=1);

use app\logic\BudgetLogic;
use app\logic\CategoryLogic;
use app\logic\HomeLogic;
use app\logic\RecordLogic;
use app\model\BudgetModel;
use app\model\CategoryModel;
use app\model\RecordModel;

$runner->test('record logic creates expense record with amount in cents', function () use ($runner): void {
    $recordModel = new RecordModel();
    $categoryModel = new CategoryModel();
    $logic = new RecordLogic($recordModel, $categoryModel);

    $record = $logic->create(1, [
        'type' => 1,
        'category_id' => 1,
        'amount' => '12.30',
        'remark' => '午餐外卖',
        'happened_at' => 1700000000,
    ]);

    $runner->assertSame(1230, $record['amount']);
    $runner->assertSame('午餐外卖', $record['remark']);
    $runner->assertSame(1, $record['user_id']);
});

$runner->test('home summary calculates spent budget and recent records', function () use ($runner): void {
    $recordModel = new RecordModel();
    $budgetModel = new BudgetModel();
    $categoryModel = new CategoryModel();
    $recordLogic = new RecordLogic($recordModel, $categoryModel);
    $budgetLogic = new BudgetLogic($budgetModel);
    $homeLogic = new HomeLogic($recordModel, $budgetModel, $categoryModel);

    $budgetLogic->saveMonthlyBudget(1, '2023-10', '1800.00');
    $recordLogic->create(1, [
        'type' => 1,
        'category_id' => 1,
        'amount' => '6.50',
        'remark' => '抹茶拿铁',
        'happened_at' => strtotime('2023-10-14 12:00:00'),
    ]);

    $summary = $homeLogic->summary(1, '2023-10');

    $runner->assertSame(650, $summary['expense_amount']);
    $runner->assertSame(180000, $summary['budget_amount']);
    $runner->assertSame(179350, $summary['remaining_amount']);
    $runner->assertSame('抹茶拿铁', $summary['recent_records'][0]['remark']);
});

$runner->test('category logic returns preset categories grouped by type', function () use ($runner): void {
    $logic = new CategoryLogic(new CategoryModel());

    $result = $logic->list();

    $runner->assertArrayHasKey('expense', $result);
    $runner->assertArrayHasKey('income', $result);
    $runner->assertSame('餐饮', $result['expense'][0]['name']);
});
