# 萌萌记账 API 说明

## 通用规则

- 基础路径：`/api`
- 登录后接口请求头：`Authorization: Bearer <token>`
- 成功响应：`{ "code": 0, "message": "success", "data": {} }`
- 失败响应：`{ "code": 非0, "message": "错误信息", "data": [] }`

## 接口

### POST `/api/wx/login`

请求：

```json
{
  "code": "wx.login 返回的 code"
}
```

响应：

```json
{
  "token": "业务登录 token",
  "user": {
    "id": 1,
    "openid": "openid_xxx",
    "nickname": "糯米豆",
    "avatar": ""
  }
}
```

### GET `/api/home/summary`

参数：`month=2026-06`

返回本月支出、收入、预算、剩余、已用百分比、最近记录和分类列表。

### GET `/api/category/list`

登录后返回系统预置分类和当前用户自定义分类：

```json
{
  "expense": [],
  "income": []
}
```

### POST `/api/category/create`

请求：

```json
{
  "type": 1,
  "name": "咖啡"
}
```

新增当前用户自定义分类。`type=1` 支出，`type=2` 收入。

### POST `/api/user/profile/save`

请求：

```json
{
  "nickname": "糯米豆",
  "avatar": "https://example.com/avatar.png"
}
```

保存当前登录用户昵称和头像地址。

### POST `/api/record/create`

请求：

```json
{
  "type": 1,
  "category_id": 1,
  "amount": "12.30",
  "remark": "午餐外卖",
  "happened_at": 1782460800
}
```

说明：

- `type=1` 支出，`type=2` 收入。
- `amount` 前端传元，后端存分。
- `happened_at` 为 Unix 秒。

### POST `/api/record/update`

请求字段同新增，额外传 `id`。

### POST `/api/record/delete`

请求：

```json
{
  "id": 1
}
```

### GET `/api/record/list`

参数：`month=2026-06`

返回按日期分组的账单记录。

### GET `/api/record/detail`

参数：`id=1`

返回账单详情。

### GET `/api/budget/month`

参数：`month=2026-06`

返回指定月份预算。

### POST `/api/budget/save`

请求：

```json
{
  "month": "2026-06",
  "amount": "1800.00"
}
```

保存用户月总预算。
