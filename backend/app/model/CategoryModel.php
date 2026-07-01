<?php
declare(strict_types=1);

namespace app\model;

use app\common\Database;

/**
 * 分类数据模型。
 *
 * 对应 MySQL 的 categories 表；支持系统预置分类和用户自定义分类。
 */
final class CategoryModel
{
    public const FIELD_ID = 'id';
    public const FIELD_TYPE = 'type';
    public const FIELD_NAME = 'name';
    public const FIELD_ICON = 'icon';
    public const FIELD_COLOR = 'color';
    public const FIELD_USER_ID = 'user_id';
    public const TYPE_EXPENSE = 1;
    public const TYPE_INCOME = 2;

    private array $categories;
    /** @var object|null */
    private $pdo;

    /**
     * @param array $categories 测试或特殊场景注入的分类数据，留空时读取 MySQL。
     * @param object|null $pdo 数据库连接，生产默认从环境变量创建，测试可注入兼容对象。
     */
    public function __construct(array $categories = [], $pdo = null)
    {
        $this->categories = $categories;
        $this->pdo = $pdo;
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
     * 获取系统分类和当前用户启用分类并按 sort 排序。
     */
    public function allActive(int $userId): array
    {
        if ($this->categories === []) {
            $statement = $this->database()->prepare(
                'SELECT `id`, `user_id`, `type`, `name`, `icon`, `color`, `sort` FROM `categories` WHERE `status` = 1 AND (`user_id` = 0 OR `user_id` = ?) ORDER BY `type` ASC, `sort` ASC, `id` ASC'
            );
            $statement->execute([$userId]);
            return $statement->fetchAll();
        }

        $items = array_values(array_filter($this->categories, static function (array $category) use ($userId): bool {
            return (int)($category['status'] ?? 1) === 1
                && ((int)($category[self::FIELD_USER_ID] ?? 0) === 0 || (int)($category[self::FIELD_USER_ID] ?? 0) === $userId);
        }));
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
    public function findByIdAndType(int $id, int $type, int $userId): ?array
    {
        if ($this->categories === []) {
            $statement = $this->database()->prepare(
                'SELECT `id`, `user_id`, `type`, `name`, `icon`, `color`, `sort` FROM `categories` WHERE `id` = ? AND `type` = ? AND `status` = 1 AND (`user_id` = 0 OR `user_id` = ?) LIMIT 1'
            );
            $statement->execute([$id, $type, $userId]);
            $category = $statement->fetch();

            return is_array($category) ? $category : null;
        }

        foreach ($this->categories as $category) {
            if ((int)$category['id'] === $id
                && (int)$category['type'] === $type
                && (int)($category['status'] ?? 1) === 1
                && ((int)($category[self::FIELD_USER_ID] ?? 0) === 0 || (int)($category[self::FIELD_USER_ID] ?? 0) === $userId)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * 新增当前用户自定义分类。
     */
    public function createCustom(int $userId, int $type, string $name): array
    {
        $now = time();
        $icon = $type === self::TYPE_INCOME ? 'savings' : 'auto_awesome';
        $color = $type === self::TYPE_INCOME ? '#E4E2DE' : '#C4EAE6';
        $sort = 1000 + count($this->allActive($userId));
        $statement = $this->database()->prepare(
            'INSERT INTO `categories` (`user_id`, `type`, `name`, `icon`, `color`, `sort`, `status`, `created_at`, `updated_at`) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)'
        );
        $statement->execute([$userId, $type, $name, $icon, $color, $sort, $now, $now]);

        return [
            self::FIELD_ID => (int)$this->database()->lastInsertId(),
            self::FIELD_USER_ID => $userId,
            self::FIELD_TYPE => $type,
            self::FIELD_NAME => $name,
            self::FIELD_ICON => $icon,
            self::FIELD_COLOR => $color,
            'sort' => $sort,
        ];
    }

    /**
     * 获取分类数据库连接。
     *
     * @return object PDO 或测试注入的兼容对象。
     */
    private function database()
    {
        if ($this->pdo !== null) {
            return $this->pdo;
        }

        $this->pdo = Database::connection();
        return $this->pdo;
    }
}
