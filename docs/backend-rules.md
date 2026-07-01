# 后端分层规则

本项目后端按 `BaseController -> Logic -> Model` 分层。

## Controller

- 只负责取参、基础类型转换、简单必填校验、登录校验、调用 Logic、返回统一响应。
- 所有业务 Controller 继承 `app\common\BaseController`。
- 不在 Controller 中拼接 SQL、不写事务、不写复杂业务规则。

## Logic

- 负责业务规则、状态检查、归属校验、流程编排。
- 业务失败抛出 `BusinessException`。
- 只通过 Model 访问数据。

## Model

- 负责字段常量、基础查询、写入、更新、删除、统计。
- 表字段通过常量集中表达。
- 多表数据按阶段查询后在 Logic 层组装，不使用左右连接。

## 数据库

- MySQL 8，`utf8mb4`，`utf8mb4_unicode_ci`，`InnoDB`。
- 业务时间字段统一为 `INT(10)` Unix 秒。
- 类型和状态字段使用 `TINYINT(1)`。
- 月预算通过 `user_id + month` 唯一约束避免重复写入。
