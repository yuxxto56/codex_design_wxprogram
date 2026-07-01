<?php
declare(strict_types=1);

namespace app\logic;

use app\common\BusinessException;
use app\common\ErrorCode;
use app\model\CategoryModel;

/**
 * 分类业务逻辑。
 *
 * 把 Model 返回的分类按 expense/income 分组，便于小程序直接渲染支出和收入分类。
 */
final class CategoryLogic
{
    private CategoryModel $categoryModel;

    public function __construct(?CategoryModel $categoryModel = null)
    {
        $this->categoryModel = $categoryModel ?? new CategoryModel();
    }

    /**
     * 获取按收支类型分组后的分类列表。
     *
     * @return array{expense:array,income:array}
     */
    public function list(int $userId): array
    {
        $result = ['expense' => [], 'income' => []];
        foreach ($this->categoryModel->allActive($userId) as $category) {
            $key = (int)$category['type'] === CategoryModel::TYPE_INCOME ? 'income' : 'expense';
            $result[$key][] = $category;
        }

        return $result;
    }

    /**
     * 新增当前用户自定义分类。
     */
    public function create(int $userId, array $payload): array
    {
        $type = (int)($payload['type'] ?? 0);
        $name = trim((string)($payload['name'] ?? ''));
        if (!in_array($type, [CategoryModel::TYPE_EXPENSE, CategoryModel::TYPE_INCOME], true)) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '分类类型错误');
        }
        if ($name === '') {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '分类名称不能为空');
        }
        $nameLength = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($nameLength > 8) {
            throw new BusinessException(ErrorCode::PARAM_INVALID, '分类名称最多8个字');
        }

        return $this->categoryModel->createCustom($userId, $type, $name);
    }
}
