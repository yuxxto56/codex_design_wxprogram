INSERT INTO `categories` (`id`, `type`, `name`, `icon`, `color`, `sort`, `status`, `created_at`, `updated_at`) VALUES
(1, 1, '餐饮', 'restaurant', '#F2E1BB', 10, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(2, 1, '购物', 'shopping_bag', '#FFB7C5', 20, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(3, 1, '出行', 'directions_car', '#ACD2CE', 30, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(4, 1, '买菜', 'shopping_cart', '#EEE3AD', 40, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(5, 1, '娱乐', 'auto_awesome', '#C4EAE6', 50, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(101, 2, '工资', 'payments', '#C8ECC9', 10, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(102, 2, '兼职', 'work', '#FFDF92', 20, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()),
(103, 2, '其他', 'savings', '#E4E2DE', 30, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `icon` = VALUES(`icon`),
  `color` = VALUES(`color`),
  `sort` = VALUES(`sort`),
  `status` = VALUES(`status`),
  `updated_at` = UNIX_TIMESTAMP();
