<?php
declare(strict_types=1);

namespace app\logic;

use app\common\BusinessException;
use app\common\ErrorCode;
use app\common\Money;
use app\model\CategoryModel;
use app\model\RecordModel;

/**
 * 账单记录业务逻辑。
 *
 * 负责收支类型、分类归属、金额格式、记录归属等业务校验，并通过 RecordModel 访问数据。
 */
final class RecordLogic
{
    private RecordModel $recordModel;
    private CategoryModel $categoryModel;

    public function __construct(?RecordModel $recordModel = null, ?CategoryModel $categoryModel = null)
    {
        $this->recordModel = $recordModel ?? new RecordModel();
        $this->categoryModel = $categoryModel ?? new CategoryModel();
    }

    /**
     * 创建收支记录。
     *
     * @param int $userId 用户 ID。
     * @param array $payload 前端提交的记录字段。
     * @return array 新建后的记录，amount 为分。
     */
    public function create(int $userId, array $payload): array
    {
        $data = $this->normalizePayload($userId, $payload);
        return $this->recordModel->create($data);
    }

    /**
     * 更新当前用户自己的收支记录。
     *
     * @param int $userId 用户 ID。
     * @param int $id 记录 ID。
     * @param array $payload 前端提交的记录字段。
     * @return array 更新后的记录。
     */
    public function update(int $userId, int $id, array $payload): array
    {
        $data = $this->normalizePayload($userId, $payload);
        $record = $this->recordModel->updateByUser($userId, $id, $data);
        if ($record === null) {
            throw new BusinessException(ErrorCode::RECORD_FORBIDDEN);
        }

        return $record;
    }

    /**
     * 删除当前用户自己的收支记录。
     *
     * @param int $userId 用户 ID。
     * @param int $id 记录 ID。
     * @return array 删除结果。
     */
    public function delete(int $userId, int $id): array
    {
        if (!$this->recordModel->deleteByUser($userId, $id)) {
            throw new BusinessException(ErrorCode::RECORD_FORBIDDEN);
        }

        return ['deleted' => true];
    }

    /**
     * 获取当前用户的一条记录详情。
     *
     * @param int $userId 用户 ID。
     * @param int $id 记录 ID。
     */
    public function detail(int $userId, int $id): array
    {
        $record = $this->recordModel->findByUser($userId, $id);
        if ($record === null) {
            throw new BusinessException(ErrorCode::NOT_FOUND);
        }

        return $record;
    }

    /**
     * 按月份查询记录并按日期分组。
     *
     * @param int $userId 用户 ID。
     * @param string $month 月份，格式 YYYY-MM。
     */
    public function list(int $userId, string $month): array
    {
        $groups = [];
        foreach ($this->recordModel->listByMonth($userId, $month) as $record) {
            $day = date('Y-m-d', (int)$record['happened_at']);
            $groups[$day][] = $record;
        }

        return ['month' => $month, 'groups' => $groups];
    }

    /**
     * 统一整理和校验前端提交的账单参数。
     *
     * @param int $userId 用户 ID。
     * @param array $payload 前端提交的记录字段。
     * @return array 可写入 Model 的标准记录结构。
     */
    private function normalizePayload(int $userId, array $payload): array
    {
        $type = (int)($payload['type'] ?? 0);
        $categoryId = (int)($payload['category_id'] ?? 0);
        if (!in_array($type, [RecordModel::TYPE_EXPENSE, RecordModel::TYPE_INCOME], true)) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '收支类型错误');
        }
        if ($this->categoryModel->findByIdAndType($categoryId, $type) === null) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '分类不存在');
        }

        return [
            'user_id' => $userId,
            'type' => $type,
            'category_id' => $categoryId,
            'amount' => Money::yuanToCent((string)($payload['amount'] ?? '')),
            'remark' => trim((string)($payload['remark'] ?? '')),
            'happened_at' => (int)($payload['happened_at'] ?? time()),
        ];
    }
}
