<?php
declare(strict_types=1);

namespace app\model;

/**
 * 分类数据模型。
 *
 * 对应 MySQL 的 categories 表；MVP 使用系统预置分类，不提供用户自定义分类。
 */
final class CategoryModel
{
    public const FIELD_ID = 'id';
    public const FIELD_TYPE = 'type';
    public const FIELD_NAME = 'name';
    public const FIELD_ICON = 'icon';
    public const FIELD_COLOR = 'color';
    public const TYPE_EXPENSE = 1;
    public const TYPE_INCOME = 2;

    private array $categories;

    /**
     * @param array $categories 测试或特殊场景注入的分类数据，留空时使用默认预置分类。
     */
    public function __construct(array $categories = [])
    {
        $this->categories = $categories !== [] ? $categories : self::preset();
    }

    /**
     * 获取系统预置分类。
     *
     * @return array 每项包含 id、type、name、icon、color、sort。
     */
    public static function preset(): array
    {
        return [
            ['id' => 1, 'type' => self::TYPE_EXPENSE, 'name' => '餐饮', 'icon' => 'restaurant', 'color' => '#F2E1BB', 'sort' => 10],
            ['id' => 2, 'type' => self::TYPE_EXPENSE, 'name' => '购物', 'icon' => 'shopping_bag', 'color' => '#FFB7C5', 'sort' => 20],
            ['id' => 3, 'type' => self::TYPE_EXPENSE, 'name' => '出行', 'icon' => 'directions_car', 'color' => '#ACD2CE', 'sort' => 30],
            ['id' => 4, 'type' => self::TYPE_EXPENSE, 'name' => '买菜', 'icon' => 'shopping_cart', 'color' => '#EEE3AD', 'sort' => 40],
            ['id' => 5, 'type' => self::TYPE_EXPENSE, 'name' => '娱乐', 'icon' => 'auto_awesome', 'color' => '#C4EAE6', 'sort' => 50],
            ['id' => 101, 'type' => self::TYPE_INCOME, 'name' => '工资', 'icon' => 'payments', 'color' => '#C8ECC9', 'sort' => 10],
            ['id' => 102, 'type' => self::TYPE_INCOME, 'name' => '兼职', 'icon' => 'work', 'color' => '#FFDF92', 'sort' => 20],
            ['id' => 103, 'type' => self::TYPE_INCOME, 'name' => '其他', 'icon' => 'savings', 'color' => '#E4E2DE', 'sort' => 30],
        ];
    }

    /**
     * 获取启用分类并按 sort 排序。
     */
    public function allActive(): array
    {
        $items = $this->categories;
        usort($items, static fn(array $a, array $b): int => $a['sort'] <=> $b['sort']);
        return $items;
    }

    /**
     * 按分类 ID 和收支类型查找分类。
     *
     * 用于记账时校验“支出记录不能选收入分类”这类业务规则。
     *
     * @param int $id 分类 ID。
     * @param int $type 收支类型：1支出，2收入。
     */
    public function findByIdAndType(int $id, int $type): ?array
    {
        foreach ($this->categories as $category) {
            if ((int)$category['id'] === $id && (int)$category['type'] === $type) {
                return $category;
            }
        }

        return null;
    }
}
