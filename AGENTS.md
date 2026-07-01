# 项目规则：萌萌记账微信小程序

## 全局沟通

- 每次回复必须使用中文。
- 每次回复结尾必须包含：`♡`
- 项目生成文件统一使用 UTF-8 编码。

## 项目结构

- `miniprogram/`：微信原生小程序前端，使用 WXML / WXSS / TypeScript。
- `backend/`：PHP 7.x + ThinkPHP 6 风格后端。
- `backend/app/controller/`：API Controller。
- `backend/app/logic/`：业务逻辑层。
- `backend/app/model/`：数据访问层。
- `backend/database/`：MySQL 建表和初始化数据脚本。
- `backend/scripts/`：数据库初始化、健康检查等独立脚本。
- `docs/`：接口说明和后端规则文档。

## 后端分层

- 后端按 `BaseController -> Logic -> Model` 分层。
- 所有业务 Controller 必须继承 `app\common\BaseController`。
- Controller 只负责逐个读取参数、基础校验、登录校验、调用 Logic、返回统一响应。
- Controller 不写复杂业务规则、不拼 SQL、不写事务、不跨 Model 编排。
- Logic 负责业务规则、状态检查、归属校验、流程编排和事务边界。
- Logic 只能通过 Model 访问数据。
- Model 负责字段常量、基础查询、写入、更新、删除、统计。
- 多表关系按阶段查询后在 Logic 层组装，不使用 `LEFT JOIN` / `RIGHT JOIN`。

## Controller 参数规则

- Controller action 方法保持无请求上下文入参，例如 `create()`、`save()`、`summary()`。
- 禁止业务 action 直接接收整包请求参数，例如 `array $body`、`array $query`、`Request $request`。
- 禁止业务 action 直接接收登录用户参数，例如 `int $userId`。
- Controller 必须通过 `BaseController` 封装方法逐个取参：
  - GET 字符串：`getRequiredString()` / `getOptionalString()`
  - POST 字符串：`postRequiredString()` / `postOptionalString()`
  - GET 整数：`getInt()`
  - POST 整数：`postInt()`
  - 当前用户：`currentUserId()`
- `BaseController` 内部优先使用 TP6 `think\facade\Request::get()` / `Request::post()`。
- 本地 `php -S` 环境可通过 `setRequestContext()` 注入 query/body/userId 作为 fallback。

## API 响应

- API 统一返回 JSON：`{ code, message, data }`。
- 成功：`code = 0`，`message = "success"`，`data` 返回真实业务数据。
- 失败：`code != 0`，`message` 返回错误原因，`data` 必须为空数组 `[]`。
- 登录后接口使用请求头：`Authorization: Bearer <token>`。
- GET 参数来自 query string；POST 参数来自 body。
- 写操作优先使用 POST。

## 数据库规则

- 使用 MySQL 8。
- 字符集：`utf8mb4`。
- 排序规则：`utf8mb4_unicode_ci`。
- 引擎：`InnoDB`。
- 每张表必须有表注释。
- 每个字段必须有字段注释。
- 业务时间字段统一使用 Unix 秒：`INT(10) NOT NULL DEFAULT 0`。
- 类型、状态、开关字段使用 `TINYINT(1)`。
- 不使用 `ENUM`。
- 金额后端统一使用整数“分”存储，前端展示为元。
- 月预算通过 `user_id + month` 唯一约束避免重复写入。

## 数据库初始化验证

- 不能把“API 服务可访问”当作“数据库已连接或库表已创建”。
- 初始化顺序必须是：
  1. 检查 `.env` 数据库配置。
  2. 运行 `php backend/scripts/check_database.php` 检查连接、库、表、种子数据。
  3. 需要建表或导入数据时运行 `php backend/scripts/init_database.php`。
  4. 再运行后端测试和 API smoke test。
- 只有 `connected=true`、`database_exists=true`、`tables_ready=true`、`seed_ready=true` 同时成立，才能说库表和初始化数据完成。
- 数据库连接失败时必须明确说明原因，例如用户名密码错误、端口未开放、目标库不存在、权限不足或 SQL 执行失败。

## 业务存储规则

- 业务数据必须读写 MySQL，不允许静默回退到本地 JSON、内存数组或临时文件。
- 登录用户、系统分类、记账记录、月预算等核心业务数据必须能在对应数据库表中查到。
- 数据库配置缺失、`pdo_mysql` 缺失、连接失败或写入失败时，接口必须明确失败，不允许返回“成功”假象。
- 本地开发、测试、生产环境都应使用同一套 Model 存储路径；测试可注入 fake PDO，但不能改变生产代码的存储分支。
- 引入任何临时存储、mock 存储或 fallback 存储前，必须先确认不会进入真实业务链路，并补静态测试防止误用。
- 验证登录链路时，不能只看接口返回 token，还必须确认 `users` 表已写入或已查到对应用户。

## 登录鉴权规则

- 微信一键登录必须使用真实 `wx.login` code，不保留 `mock_` code 登录分支。
- 前端小程序 AppID、后端 `WECHAT_APPID`、后端 `WECHAT_SECRET` 必须属于同一个微信小程序。
- `invalid code` 优先排查 code 是否过期、AppID 是否一致、AppSecret 是否正确、微信开发者工具项目配置是否使用真实 AppID。
- token 签发和解析必须使用同一个密钥来源，例如统一读取 `APP_SECRET`；不允许登录接口和业务接口各自使用不同默认密钥。
- 登录后接口必须携带 `Authorization: Bearer <token>`，排查鉴权问题时要同时确认请求头、token 解析、用户表查询三段链路。
- 不在日志、报错或文档中输出完整 token、AppSecret、数据库密码等敏感信息。

## 运行环境规则

- PHP 运行镜像版本必须和代码语法兼容；使用 PHP 8 语法时，生产镜像不得停留在 PHP 7.3。
- 生产和本地容器必须启用 `pdo_mysql`，否则涉及数据库的接口应明确报错。
- 容器启动日志中出现扩展 `.so` 加载失败时，先修复镜像扩展配置，再判断业务代码问题。
- `vendor/` 由 Composer 安装生成；仓库没有 `vendor/` 时，应检查 `composer.json` 和部署流水线是否执行 `composer install`。
- 本地 `127.0.0.1` 调试小程序接口时，仅限微信开发者工具中勾选“不校验合法域名、web-view、TLS 版本以及 HTTPS 证书”；真机和线上必须使用合法 HTTPS 域名。

## 小程序前端交互规则

- 自定义键盘、底部导航、分类网格等固定格式 UI 必须使用稳定数据源渲染，避免在 `wx:for` 后追加零散按钮造成布局错位。
- 微信 `button` 用在网格中时应显式设置 `width: 100%`、`min-width: 0`、`margin: 0`，避免默认样式挤出列宽。
- 微信原生 `button` 不能依赖默认高度、默认 padding 或单纯 `line-height` 做文字居中；页面内自定义按钮必须显式设置稳定 `height`、`padding: 0`、`display: flex`、`align-items: center`、`justify-content: center` 和 `line-height: normal`。
- 微信原生 `button` 的默认边框必须通过 `button::after { border: 0; }` 或对应类名的 `::after` 清除，避免视觉尺寸和对齐被默认样式影响。
- 调整按钮文字居中时不能只改 `display: flex`；必须同步确认按钮是否有明确高度和字号，避免失去微信默认高度后出现按钮变矮、与页面其他按钮不协调的问题。
- 页面切换关键状态时要同步重置相关输入状态，例如支出/收入切换后金额应清零、分类应切换到当前类型默认分类。
- 不保留重复提交入口；如果底部已有“保存”，键盘内不再放 `OK` 保存键。
- 前端修复完成后至少在微信开发者工具重新编译，并检查 375px 宽度下文本、按钮和网格不重叠、不缺列。
- 小程序页面路径变更时，必须同时检查 `app.json`、对应 `.wxml/.wxss/.ts` 文件和开发者工具编译产物。

## 小程序前端

- 前端采用微信原生小程序 TypeScript。
- 页面文案以中文为主。
- 视觉风格保持“萌萌记账 / Pastel Ledger”：柔和粉黄绿、圆角卡片、轻拟物、记账日记感。
- API 地址在 `miniprogram/app.ts` 的 `apiBaseUrl` 中配置。
- 本地调试也使用真实微信登录，需要配置同一个小程序的 AppID 和 Secret。

## 验证命令

- 后端测试：
  ```bash
  php backend/tests/run.php
  ```
- PHP 语法检查：
  ```powershell
  Get-ChildItem -Recurse backend -Filter *.php | ForEach-Object { php -l $_.FullName }
  ```
- 数据库检查：
  ```bash
  php backend/scripts/check_database.php
  ```
- 数据库初始化：
  ```bash
  php backend/scripts/init_database.php
  ```
- 本地 API 服务：
  ```bash
  php -S 127.0.0.1:8010 -t backend/public backend/public/index.php
  ```

## 开发纪律

- 修改行为前优先补测试或静态约束。
- 不要删除或回滚用户已有改动，除非用户明确要求。
- 后端接口、数据库结构、前端请求字段变更时，同步更新测试、文档和 README。
- 提交前至少运行后端测试、PHP 语法检查；涉及数据库时必须运行数据库检查脚本。
- 每次提交前先确认 `git status -sb`，区分本次改动、用户已有改动和未跟踪文件。
- 推送远程前必须明确暂存范围，避免把无关的 `docs/`、`miniprogram/`、`AGENTS.md` 或历史实验文件误带入提交。
- 对“接口已启动”“登录成功”“数据库完成”“已推送”这类状态描述，必须有命令输出或平台反馈作为依据。
