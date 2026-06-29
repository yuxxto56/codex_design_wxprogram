# 萌萌记账微信小程序 MVP

这是一个个人记账类微信小程序 MVP，包含微信原生小程序 TypeScript 前端和 PHP 7.x + ThinkPHP 6 风格后端骨架。

## 目录

- `miniprogram/`：微信原生小程序，使用 WXML/WXSS/TypeScript。
- `backend/`：ThinkPHP 6 API 分层代码、数据库脚本和轻量测试。

## 后端

1. 复制配置：
   ```bash
   cp backend/.env.example backend/.env
   ```
2. 修改微信和数据库配置：
   - `WECHAT_APPID`
   - `WECHAT_SECRET`
   - `APP_SECRET`
   - `DATABASE_*`
3. 检查数据库连接：
   ```bash
   php backend/scripts/check_database.php
   ```
   这个命令会明确输出 `connected`、`database_exists`、`tables_ready`、`seed_ready`。如果连接失败，会输出具体失败原因。
4. 初始化数据库和种子数据：
   ```bash
   php backend/scripts/init_database.php
   ```
   只有该命令输出 `connected=true`、`database_exists=true`、`tables_ready=true`、`seed_ready=true` 时，才能认为库表和初始化数据已完成。
5. 运行测试：
   ```bash
   php backend/tests/run.php
   ```
6. 开发环境启动 mock API：
   ```bash
   php -S 127.0.0.1:8010 -t backend/public backend/public/index.php
   ```

当前后端提供 ThinkPHP 6 项目所需的 Controller / Logic / Model 分层骨架。生产接入时应将 Model 内存实现替换为 ThinkPHP ORM 或查询构造器封装，但仍保持 Logic 只通过 Model 访问数据。

注意：`http://127.0.0.1:8010/` 只能证明 API 服务启动，不代表 MySQL 已连接或库表已创建。数据库状态以 `backend/scripts/check_database.php` 的输出为准。

## 小程序

1. 使用微信开发者工具打开 `miniprogram/`。
2. 在 `miniprogram/project.config.json` 将 `appid` 从 `touristappid` 改成微信公众平台的小程序 AppID。
3. 在 `backend/.env` 配置同一个小程序的 `WECHAT_APPID` 和 `WECHAT_SECRET`。
4. 在 `miniprogram/app.ts` 修改 `apiBaseUrl` 为本地或测试环境 API 地址。
5. 开发环境可在登录页点击“开发环境模拟登录”，对应后端 `mock_` code 流程。
6. 真机上线前需要在微信公众平台配置合法请求域名。

如果点击“微信一键登录”提示“请配置 WECHAT_APPID 和 WECHAT_SECRET 后再使用微信一键登录”，说明后端还没有读取到真实的小程序 AppID/Secret，或 `.env` 仍保留占位值。

## MVP 功能

- 微信登录 / 开发模拟登录
- 首页本月支出、预算、剩余、最近记录
- 新增收入 / 支出记录
- 按月份查看账单，支持删除
- 我的页面和月预算设置
- 系统预置分类

## 编码约定

项目文件使用 UTF-8 编码。接口响应统一为：

```json
{
  "code": 0,
  "message": "success",
  "data": {}
}
```

错误响应保持 `data` 为空数组。
