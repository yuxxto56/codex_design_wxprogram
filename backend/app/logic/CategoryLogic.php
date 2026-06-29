<?php
declare(strict_types=1);

namespace app\logic;

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
    public function list(): array
    {
        $result = ['expense' => [], 'income' => []];
        foreach ($this->categoryModel->allActive() as $category) {
            $key = (int)$category['type'] === CategoryModel::TYPE_INCOME ? 'income' : 'expense';
            $result[$key][] = $category;
        }

        return $result;
    }
}
