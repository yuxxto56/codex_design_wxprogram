CREATE DATABASE IF NOT EXISTS `mengmeng_ledger`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `mengmeng_ledger`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `openid` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '微信小程序openid，唯一标识用户',
  `nickname` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '用户昵称，默认糯米豆',
  `avatar` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '用户头像URL',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '用户状态：1正常，0禁用',
  `created_at` INT(10) NOT NULL DEFAULT 0 COMMENT '创建时间，Unix秒',
  `updated_at` INT(10) NOT NULL DEFAULT 0 COMMENT '更新时间，Unix秒',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_openid` (`openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='微信小程序用户表';

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '分类ID',
  `user_id` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属用户ID，0表示系统分类',
  `type` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '分类类型：1支出，2收入',
  `name` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '分类名称',
  `icon` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '小程序图标标识',
  `color` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '分类展示颜色',
  `sort` INT(10) NOT NULL DEFAULT 0 COMMENT '排序值，越小越靠前',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1启用，0停用',
  `created_at` INT(10) NOT NULL DEFAULT 0 COMMENT '创建时间，Unix秒',
  `updated_at` INT(10) NOT NULL DEFAULT 0 COMMENT '更新时间，Unix秒',
  PRIMARY KEY (`id`),
  KEY `idx_type_status_sort` (`type`, `status`, `sort`),
  KEY `idx_user_type_status_sort` (`user_id`, `type`, `status`, `sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统预置收支分类表';

CREATE TABLE IF NOT EXISTS `ledger_records` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `user_id` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属用户ID',
  `type` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '记录类型：1支出，2收入',
  `category_id` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '分类ID',
  `amount` INT(10) NOT NULL DEFAULT 0 COMMENT '金额，单位分',
  `remark` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '备注说明',
  `happened_at` INT(10) NOT NULL DEFAULT 0 COMMENT '业务发生时间，Unix秒',
  `created_at` INT(10) NOT NULL DEFAULT 0 COMMENT '创建时间，Unix秒',
  `updated_at` INT(10) NOT NULL DEFAULT 0 COMMENT '更新时间，Unix秒',
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`, `happened_at`),
  KEY `idx_user_type_time` (`user_id`, `type`, `happened_at`),
  KEY `idx_category` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='个人收支记账记录表';

CREATE TABLE IF NOT EXISTS `monthly_budgets` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '预算ID',
  `user_id` INT(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属用户ID',
  `month` CHAR(7) NOT NULL DEFAULT '' COMMENT '预算月份，格式YYYY-MM',
  `amount` INT(10) NOT NULL DEFAULT 0 COMMENT '月总预算金额，单位分',
  `created_at` INT(10) NOT NULL DEFAULT 0 COMMENT '创建时间，Unix秒',
  `updated_at` INT(10) NOT NULL DEFAULT 0 COMMENT '更新时间，Unix秒',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_month` (`user_id`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户月总预算表';
