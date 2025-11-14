# CourseGenius API 文档

## 概述

CourseGenius 提供 RESTful API 接口，支持课程管理、章节管理、AI生成等功能。

**基础URL**：`http://your-domain.com`

**响应格式**：JSON

## 通用响应格式

### 成功响应

```json
{
  "code": 1,
  "message": "操作成功",
  "data": {},
  "time": 1234567890
}
```

### 失败响应

```json
{
  "code": 0,
  "message": "错误信息",
  "data": {},
  "time": 1234567890
}
```

## 认证接口

### 用户登录

**接口**：`POST /login`

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | 是 | 用户名或邮箱 |
| password | string | 是 | 密码 |
| remember | int | 否 | 记住登录（0/1） |

**请求示例**：

```json
{
  "username": "admin",
  "password": "admin123",
  "remember": 1
}
```

**成功响应**：

```json
{
  "code": 1,
  "message": "登录成功",
  "data": {
    "user_id": 1,
    "username": "admin"
  }
}
```

### 用户注册

**接口**：`POST /register`

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | 是 | 用户名（3-50字符） |
| email | string | 是 | 邮箱 |
| password | string | 是 | 密码（6-20字符） |
| password_confirm | string | 是 | 确认密码 |

**成功响应**：

```json
{
  "code": 1,
  "message": "注册成功",
  "data": {
    "user_id": 2
  }
}
```

### 退出登录

**接口**：`GET /logout`

**成功响应**：

```json
{
  "code": 1,
  "message": "退出成功",
  "data": {}
}
```

### 获取用户信息

**接口**：`GET /auth/userInfo`

**需要认证**：是

**成功响应**：

```json
{
  "code": 1,
  "message": "操作成功",
  "data": {
    "id": 1,
    "username": "admin",
    "email": "admin@example.com",
    "nickname": "管理员",
    "user_level": 3,
    "level_name": "专业版",
    "api_quota_daily": 1000,
    "api_used_today": 15
  }
}
```

## 课程管理接口

### 创建课程

**接口**：`POST /course/create`

**需要认证**：是

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| course_title | string | 是 | 课程标题 |
| course_description | string | 否 | 课程描述 |
| course_topic | string | 是 | 课程主题（详细描述） |
| target_audience | string | 否 | 目标受众：beginner/intermediate/advanced |
| content_style | string | 否 | 内容风格：formal/casual/humorous/practical |
| chapter_count_suggest | int | 否 | 建议章节数（默认10） |
| depth_level | int | 否 | 目录级数：2/3/4/5（默认3） |
| word_count_per_chapter | int | 否 | 每章字数（默认1500） |
| include_code | int | 否 | 包含代码：0/1 |
| include_exercises | int | 否 | 包含练习题：0/1 |
| special_requirements | string | 否 | 特殊要求 |

**请求示例**：

```json
{
  "course_title": "Python全栈开发从入门到实战",
  "course_description": "一门完整的Python全栈开发课程",
  "course_topic": "这是一门面向零基础学员的Python全栈开发课程，涵盖Python基础、Web开发、数据库、前端技术等内容",
  "target_audience": "beginner",
  "content_style": "practical",
  "chapter_count_suggest": 15,
  "depth_level": 3,
  "word_count_per_chapter": 2000,
  "include_code": 1,
  "include_exercises": 1
}
```

**成功响应**：

```json
{
  "code": 1,
  "message": "课程创建成功",
  "data": {
    "course_id": 1
  }
}
```

### 获取课程列表

**接口**：`GET /course/index`

**需要认证**：是

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| page | int | 否 | 页码（默认1） |
| limit | int | 否 | 每页数量（默认10） |
| status | int | 否 | 状态筛选 |
| keyword | string | 否 | 搜索关键词 |

**成功响应**：

```json
{
  "code": 0,
  "msg": "",
  "count": 100,
  "data": [
    {
      "id": 1,
      "course_title": "Python全栈开发",
      "status": 4,
      "status_text": "已完成",
      "total_chapters": 15,
      "generated_chapters": 15,
      "total_words": 30000,
      "progress": 100,
      "create_time": "2024-01-01 10:00:00"
    }
  ]
}
```

### 获取课程详情

**接口**：`GET /course/detail/:id`

**需要认证**：是

**成功响应**：

```json
{
  "code": 1,
  "message": "操作成功",
  "data": {
    "id": 1,
    "course_title": "Python全栈开发",
    "course_description": "...",
    "status": 4,
    "status_text": "已完成",
    "total_chapters": 15,
    "generated_chapters": 15,
    "total_words": 30000,
    "progress": 100
  }
}
```

### 更新课程

**接口**：`POST /course/edit/:id`

**需要认证**：是

**请求参数**：与创建课程相同

### 删除课程

**接口**：`POST /course/delete/:id`

**需要认证**：是

**成功响应**：

```json
{
  "code": 1,
  "message": "课程删除成功",
  "data": {}
}
```

### 获取章节树

**接口**：`GET /course/getChapterTree`

**需要认证**：是

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| course_id | int | 是 | 课程ID |

**成功响应**：

```json
{
  "code": 1,
  "message": "操作成功",
  "data": [
    {
      "id": 1,
      "chapter_number": "1",
      "chapter_title": "第1章：Python基础",
      "status": 2,
      "children": [
        {
          "id": 2,
          "chapter_number": "1.1",
          "chapter_title": "1.1 Python简介",
          "status": 2
        }
      ]
    }
  ]
}
```

### 用户统计

**接口**：`GET /course/stats`

**需要认证**：是

**成功响应**：

```json
{
  "code": 1,
  "message": "操作成功",
  "data": {
    "total_courses": 10,
    "completed_courses": 5,
    "total_chapters": 150,
    "generated_chapters": 120,
    "total_words": 180000
  }
}
```

## 章节管理接口

### 获取章节详情

**接口**：`GET /chapter/detail/:id`

**需要认证**：是

**成功响应**：

```json
{
  "code": 1,
  "message": "操作成功",
  "data": {
    "id": 1,
    "chapter_number": "1.1",
    "chapter_title": "Python简介",
    "chapter_description": "...",
    "content": "# Python简介\n\n...",
    "content_html": "<h1>Python简介</h1>...",
    "word_count": 1500,
    "status": 2,
    "status_text": "已生成"
  }
}
```

### 保存章节内容

**接口**：`POST /chapter/save`

**需要认证**：是

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| chapter_id | int | 是 | 章节ID |
| content | string | 是 | Markdown内容 |

**成功响应**：

```json
{
  "code": 1,
  "message": "章节更新成功",
  "data": {}
}
```

### 添加章节

**接口**：`POST /chapter/add`

**需要认证**：是

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| course_id | int | 是 | 课程ID |
| parent_id | int | 否 | 父章节ID（0为根节点） |
| chapter_number | string | 是 | 章节编号 |
| chapter_title | string | 是 | 章节标题 |
| chapter_description | string | 否 | 章节描述 |

### 删除章节

**接口**：`POST /chapter/delete/:id`

**需要认证**：是

### 版本历史

**接口**：`GET /chapter/versionHistory`

**需要认证**：是

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| chapter_id | int | 是 | 章节ID |

**成功响应**：

```json
{
  "code": 1,
  "message": "操作成功",
  "data": [
    {
      "id": 1,
      "version_number": 3,
      "word_count": 1500,
      "change_description": "手动编辑",
      "create_time": "2024-01-01 10:00:00"
    }
  ]
}
```

## AI生成接口

### 生成课程大纲

**接口**：`POST /generate/generateOutline`

**需要认证**：是

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| course_id | int | 是 | 课程ID |

**成功响应**：

```json
{
  "code": 1,
  "message": "大纲生成任务已创建，请稍后查看",
  "data": {
    "task_id": 1
  }
}
```

### 生成章节内容（单个）

**接口**：`POST /generate/generateChapter`

**需要认证**：是

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| chapter_id | int | 是 | 章节ID |

**成功响应**：

```json
{
  "code": 1,
  "message": "内容生成任务已创建，请稍后查看",
  "data": {
    "task_id": 2
  }
}
```

### 批量生成章节

**接口**：`POST /generate/batchGenerate`

**需要认证**：是

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| course_id | int | 是 | 课程ID |

**成功响应**：

```json
{
  "code": 1,
  "message": "已创建 15 个生成任务",
  "data": {
    "count": 15,
    "task_ids": [1, 2, 3, ...]
  }
}
```

### 获取任务状态

**接口**：`GET /generate/taskStatus/:task_id`

**需要认证**：是

**成功响应**：

```json
{
  "code": 1,
  "message": "操作成功",
  "data": {
    "status": 2,
    "status_text": "已完成",
    "error_message": ""
  }
}
```

**状态说明**：
- 0：待处理
- 1：处理中
- 2：已完成
- 3：失败

### AI文本优化

**接口**：`POST /generate/optimizeText`

**需要认证**：是

**请求参数**：

| 参数 | 类型 | 必填 | 说明 |
|------|------|------|------|
| text | string | 是 | 原文本 |
| action | string | 是 | 操作：rewrite/expand/shorten/translate |

**成功响应**：

```json
{
  "code": 1,
  "message": "优化成功",
  "data": {
    "result": "优化后的文本内容...",
    "token_used": 150
  }
}
```

### 测试API连接

**接口**：`GET /generate/testApi`

**需要认证**：是

**成功响应**：

```json
{
  "code": 1,
  "message": "API连接成功",
  "data": {
    "message": "API连接成功",
    "token_used": 50,
    "preview": "测试内容预览..."
  }
}
```

## 错误码说明

| 错误码 | 说明 |
|--------|------|
| 0 | 操作失败 |
| 1 | 操作成功 |
| 401 | 未登录 |
| 403 | 无权限 |
| 404 | 资源不存在 |
| 500 | 服务器错误 |

## 请求示例（JavaScript）

### 使用 Fetch API

```javascript
// 登录
async function login(username, password) {
  const response = await fetch('/login', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      username,
      password,
      remember: 1
    })
  });

  const data = await response.json();

  if (data.code === 1) {
    console.log('登录成功', data.data);
  } else {
    console.error('登录失败', data.message);
  }
}

// 创建课程
async function createCourse(courseData) {
  const response = await fetch('/course/create', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(courseData)
  });

  const data = await response.json();
  return data;
}

// 生成大纲
async function generateOutline(courseId) {
  const response = await fetch('/generate/generateOutline', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ course_id: courseId })
  });

  const data = await response.json();
  return data;
}

// 轮询任务状态
async function pollTaskStatus(taskId) {
  const response = await fetch(`/generate/taskStatus/${taskId}`);
  const data = await response.json();

  if (data.data.status === 2) {
    console.log('任务完成');
  } else if (data.data.status === 3) {
    console.error('任务失败', data.data.error_message);
  } else {
    // 继续轮询
    setTimeout(() => pollTaskStatus(taskId), 3000);
  }
}
```

### 使用 Axios

```javascript
import axios from 'axios';

// 登录
const login = async (username, password) => {
  try {
    const { data } = await axios.post('/login', {
      username,
      password,
      remember: 1
    });

    if (data.code === 1) {
      console.log('登录成功', data.data);
    }
  } catch (error) {
    console.error('请求失败', error);
  }
};

// 获取课程列表
const getCourseList = async (page = 1) => {
  try {
    const { data } = await axios.get('/course/index', {
      params: { page, limit: 10 }
    });

    return data.data;
  } catch (error) {
    console.error('获取失败', error);
  }
};
```

## 限流说明

- **API配额**：每个用户有每日API调用次数限制
- **免费版**：每日50次
- **基础版**：每日200次
- **专业版**：每日1000次

超出配额后将返回错误：

```json
{
  "code": 0,
  "message": "API配额不足，请明天再试或升级套餐",
  "data": {}
}
```

## WebSocket 推送（计划中）

计划支持 WebSocket 实时推送生成进度，避免客户端频繁轮询。

---

如有疑问，欢迎提交 Issue！
