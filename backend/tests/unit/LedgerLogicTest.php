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
    $pdo = new FakePdo();
    $recordModel = new RecordModel($pdo);
    $categoryModel = new CategoryModel([], $pdo);
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
    $pdo = new FakePdo();
    $recordModel = new RecordModel($pdo);
    $budgetModel = new BudgetModel($pdo);
    $categoryModel = new CategoryModel([], $pdo);
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

$runner->test('record list groups early morning records by Asia Shanghai day', function () use ($runner): void {
    $previousTimezone = date_default_timezone_get();
    date_default_timezone_set('UTC');

    try {
        $pdo = new FakePdo();
        $recordModel = new RecordModel($pdo);
        $categoryModel = new CategoryModel([], $pdo);
        $logic = new RecordLogic($recordModel, $categoryModel);
        $happenedAt = (new DateTimeImmutable('2026-06-30 02:00:09', new DateTimeZone('Asia/Shanghai')))->getTimestamp();

        $logic->create(1, [
            'type' => 1,
            'category_id' => 1,
            'amount' => '58.00',
            'remark' => 'early morning dinner',
            'happened_at' => $happenedAt,
        ]);

        $list = $logic->list(1, '2026-06');

        $runner->assertArrayHasKey('2026-06-30', $list['groups']);
        $runner->assertSame('early morning dinner', $list['groups']['2026-06-30'][0]['remark']);
    } finally {
        date_default_timezone_set($previousTimezone);
    }
});

$runner->test('record logic returns latest month with records for current user', function () use ($runner): void {
    $pdo = new FakePdo();
    $recordModel = new RecordModel($pdo);
    $categoryModel = new CategoryModel([], $pdo);
    $logic = new RecordLogic($recordModel, $categoryModel);

    $logic->create(1, [
        'type' => 1,
        'category_id' => 1,
        'amount' => '12.00',
        'remark' => 'May record',
        'happened_at' => (new DateTimeImmutable('2026-05-18 12:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp(),
    ]);
    $logic->create(1, [
        'type' => 1,
        'category_id' => 1,
        'amount' => '18.00',
        'remark' => 'June record',
        'happened_at' => (new DateTimeImmutable('2026-06-30 22:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp(),
    ]);
    $logic->create(2, [
        'type' => 1,
        'category_id' => 1,
        'amount' => '20.00',
        'remark' => 'Other user July record',
        'happened_at' => (new DateTimeImmutable('2026-07-01 10:00:00', new DateTimeZone('Asia/Shanghai')))->getTimestamp(),
    ]);

    $runner->assertSame(['month' => '2026-06'], $logic->latestMonth(1));
});

$runner->test('category logic returns preset categories grouped by type', function () use ($runner): void {
    $logic = new CategoryLogic(new CategoryModel([], new FakePdo()));

    $result = $logic->list(1);

    $runner->assertArrayHasKey('expense', $result);
    $runner->assertArrayHasKey('income', $result);
    $runner->assertSame('餐饮', $result['expense'][0]['name']);
});

$runner->test('category logic creates user category and hides it from other users', function () use ($runner): void {
    $pdo = new FakePdo();
    $logic = new CategoryLogic(new CategoryModel([], $pdo));

    $created = $logic->create(1, [
        'type' => 1,
        'name' => '咖啡',
    ]);
    $ownerList = $logic->list(1);
    $otherList = $logic->list(2);

    $runner->assertSame(1, $created['user_id']);
    $runner->assertSame('咖啡', $created['name']);
    $runner->assertTrue(
        count(array_filter($ownerList['expense'], static fn(array $category): bool => $category['name'] === '咖啡')) === 1,
        'owner should see custom category'
    );
    $runner->assertTrue(
        count(array_filter($otherList['expense'], static fn(array $category): bool => $category['name'] === '咖啡')) === 0,
        'other users should not see custom category'
    );
});

$runner->test('record logic allows own custom category and rejects another user category', function () use ($runner): void {
    $pdo = new FakePdo();
    $categoryModel = new CategoryModel([], $pdo);
    $categoryLogic = new CategoryLogic($categoryModel);
    $recordLogic = new RecordLogic(new RecordModel($pdo), $categoryModel);

    $ownCategory = $categoryLogic->create(1, ['type' => 1, 'name' => '咖啡']);
    $otherCategory = $categoryLogic->create(2, ['type' => 1, 'name' => '宠物']);
    $record = $recordLogic->create(1, [
        'type' => 1,
        'category_id' => $ownCategory['id'],
        'amount' => '18.00',
        'remark' => '拿铁',
        'happened_at' => 1700000000,
    ]);

    $runner->assertSame((int)$ownCategory['id'], (int)$record['category_id']);

    try {
        $recordLogic->create(1, [
            'type' => 1,
            'category_id' => $otherCategory['id'],
            'amount' => '20.00',
            'remark' => 'should fail',
            'happened_at' => 1700000000,
        ]);
        throw new RuntimeException('expected another user category to be rejected');
    } catch (\app\common\BusinessException $exception) {
        $runner->assertSame(\app\common\ErrorCode::PARAM_INVALID, $exception->getCode());
    }
});
