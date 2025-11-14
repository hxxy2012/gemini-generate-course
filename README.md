# CourseGenius - AI驱动的课程内容自动生成系统

![License](https://img.shields.io/badge/license-Apache%202.0-blue.svg)
![PHP Version](https://img.shields.io/badge/php-%3E%3D8.0-8892BF.svg)
![ThinkPHP](https://img.shields.io/badge/ThinkPHP-8.x-green.svg)

CourseGenius（课程天才）是一个基于 ThinkPHP 8.x + MySQL + Google Gemini API 的智能课程内容自动生成系统。用户只需输入课程主题，系统即可自动生成完整的课程大纲和详细内容，大幅提升课程创作效率。

## 🌟 核心特性

### AI智能生成
- **智能大纲生成**：根据课程主题自动生成多级结构化大纲
- **章节内容生成**：AI自动编写详细的课程内容，支持代码示例、练习题
- **内容优化**：AI辅助改写、扩写、缩写、翻译
- **上下文理解**：生成内容时考虑课程整体结构和前后章节关联

### 课程管理
- **多课程管理**：支持创建管理多个课程项目
- **树形大纲编辑**：可视化的树形结构，支持拖拽排序
- **章节版本控制**：自动保存历史版本，支持版本对比和恢复
- **实时统计**：课程进度、字数统计、生成状态一目了然

### 内容编辑
- **Markdown编辑器**：支持实时预览、语法高亮
- **代码高亮**：支持50+编程语言
- **AI辅助编辑**：选中文本即可优化、续写、翻译
- **自动保存**：防止内容丢失

### 导出功能
- **多格式导出**：Word、PDF、Markdown、HTML、ePub
- **自定义样式**：封面、字体、配色自定义
- **批量导出**：支持导出全部或选中章节

### 用户系统
- **多套餐支持**：免费版、基础版、专业版
- **API配额管理**：每日调用次数限制，自动重置
- **使用统计**：课程数、字数、Token消耗等

## 📋 技术栈

- **后端框架**：ThinkPHP 8.x
- **数据库**：MySQL 8.0+
- **AI引擎**：Google Gemini API
- **缓存**：Redis（可选）
- **队列**：ThinkPHP Queue（异步任务）
- **文档处理**：PHPWord、TCPDF、Parsedown

## 🚀 快速开始

### 环境要求

- PHP >= 8.0
- MySQL >= 8.0
- Composer
- Redis（可选）
- Gemini API Key

### 安装步骤

#### 1. 克隆项目

```bash
git clone https://github.com/your-username/course-genius.git
cd course-genius
```

#### 2. 安装依赖

```bash
composer install
```

#### 3. 配置环境

复制环境配置文件：

```bash
cp .env.example .env
```

编辑 `.env` 文件，配置数据库和Gemini API：

```ini
# 数据库配置
[DATABASE]
TYPE = mysql
HOSTNAME = 127.0.0.1
DATABASE = course_genius
USERNAME = root
PASSWORD = your_password
HOSTPORT = 3306
CHARSET = utf8mb4
PREFIX = cg_

# Gemini API配置
[GEMINI]
API_KEY = your_gemini_api_key_here
MODEL = gemini-pro
```

#### 4. 导入数据库

```bash
mysql -u root -p < database.sql
```

或手动导入 `database.sql` 文件到MySQL数据库。

#### 5. 配置Web服务器

**Apache (推荐使用.htaccess)**

确保 `public` 目录是Web根目录，并启用 `mod_rewrite`。

**Nginx**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/course-genius/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

#### 6. 设置目录权限

```bash
chmod -R 777 runtime
chmod -R 777 public/uploads
```

#### 7. 启动队列处理器

队列处理器用于异步处理AI生成任务：

```bash
php think queue:work
```

建议使用 Supervisor 或 systemd 保持队列处理器持续运行。

### 使用 Supervisor 管理队列（推荐）

创建 Supervisor 配置文件 `/etc/supervisor/conf.d/course-genius.conf`：

```ini
[program:course-genius-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/course-genius/think queue:work
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/course-genius/runtime/log/queue.log
```

启动：

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start course-genius-queue:*
```

## 📖 使用指南

### 1. 登录系统

访问 `http://your-domain.com/login`

默认管理员账户：
- 用户名：`admin`
- 密码：`admin123`

### 2. 创建课程

1. 点击「创建课程」
2. 填写课程基本信息：
   - 课程标题
   - 课程主题（详细描述）
   - 目标受众（初学者/中级/高级）
   - 建议章节数
   - 目录深度（2-5级）
   - 每章字数
   - 是否包含代码示例
   - 是否包含练习题
3. 点击「创建并生成大纲」

### 3. AI生成大纲

系统将根据你的输入自动生成课程大纲，通常需要10-30秒。

生成后可以：
- 编辑章节标题和描述
- 添加/删除章节
- 调整章节顺序（拖拽）
- 重新生成大纲

### 4. 生成章节内容

大纲确认后，可以：

- **单章生成**：点击章节右侧的「生成」按钮
- **批量生成**：点击「批量生成全部章节」

生成过程在后台队列中进行，可以实时查看进度。

### 5. 编辑内容

生成完成后，点击章节进入编辑器：

- 使用Markdown编辑内容
- 实时预览渲染效果
- 选中文本使用AI优化功能
- 支持代码高亮
- 自动保存版本历史

### 6. 导出课程

课程完成后，可以导出为：

- **Word文档** (.docx)：适合打印和编辑
- **PDF文档** (.pdf)：适合分发和阅读
- **Markdown** (.md)：适合版本控制
- **HTML网页** (.html)：适合在线阅读

## 🎨 项目结构

```
course-genius/
├── app/                        # 应用目录
│   ├── index/                  # 前台应用
│   │   ├── controller/         # 控制器
│   │   │   ├── Auth.php        # 用户认证
│   │   │   ├── Course.php      # 课程管理
│   │   │   ├── Chapter.php     # 章节管理
│   │   │   └── Generate.php    # AI生成
│   │   ├── model/              # 模型
│   │   │   ├── User.php
│   │   │   ├── Course.php
│   │   │   ├── Chapter.php
│   │   │   └── GenerateTask.php
│   │   ├── service/            # 业务服务
│   │   │   ├── GeminiService.php     # Gemini API
│   │   │   ├── CourseService.php     # 课程服务
│   │   │   ├── ChapterService.php    # 章节服务
│   │   │   └── GenerateService.php   # 生成服务
│   │   └── view/               # 视图（待开发）
│   ├── admin/                  # 后台应用（待开发）
│   ├── common/                 # 公共模块
│   │   └── BaseController.php
│   └── command/                # 命令行
│       └── QueueWork.php       # 队列处理
├── config/                     # 配置文件
│   ├── app.php
│   ├── database.php
│   ├── cache.php
│   └── gemini.php
├── public/                     # Web根目录
│   ├── index.php
│   ├── static/                 # 静态资源（待开发）
│   └── uploads/                # 上传文件
├── runtime/                    # 运行时目录
├── database.sql                # 数据库结构
├── composer.json
├── .env.example                # 环境配置示例
└── README.md                   # 本文档
```

## 🔧 配置说明

### Gemini API配置

1. 获取API Key：访问 [Google AI Studio](https://makersuite.google.com/app/apikey)
2. 编辑 `.env` 文件，填入API Key
3. 可选：修改模型（默认 `gemini-pro`）

### 用户套餐配置

在 `cg_config` 表中可以配置各套餐的限制：

- `max_chapters_free`：免费版最大章节数（默认10）
- `max_chapters_basic`：基础版最大章节数（默认50）
- `api_quota_daily_free`：免费版每日API配额（默认50）
- `api_quota_daily_basic`：基础版每日API配额（默认200）

### 生成参数配置

在 `config/gemini.php` 中可以调整生成参数：

```php
'outline_generation' => [
    'temperature' => 0.7,      // 创造性（0-1）
    'topK' => 40,
    'topP' => 0.95,
    'maxOutputTokens' => 4096, // 最大输出长度
],

'content_generation' => [
    'temperature' => 0.8,
    'topK' => 40,
    'topP' => 0.95,
    'maxOutputTokens' => 8192,
],
```

## 📊 API接口

### 课程管理

```
POST   /course/create          创建课程
GET    /course/index           课程列表
GET    /course/detail/:id      课程详情
POST   /course/edit/:id        编辑课程
POST   /course/delete/:id      删除课程
GET    /course/getChapterTree  获取章节树
```

### 章节管理

```
GET    /chapter/detail/:id     章节详情
POST   /chapter/save           保存章节内容
POST   /chapter/add            添加章节
POST   /chapter/delete/:id     删除章节
POST   /chapter/updateSort     更新排序
```

### AI生成

```
POST   /generate/generateOutline      生成大纲
POST   /generate/generateChapter      生成章节（单个）
POST   /generate/batchGenerate        批量生成
GET    /generate/taskStatus/:id       任务状态
POST   /generate/optimizeText         AI文本优化
GET    /generate/testApi              测试API连接
```

## 🛠️ 开发计划

- [x] 核心功能开发
  - [x] 用户认证系统
  - [x] 课程管理
  - [x] AI大纲生成
  - [x] AI内容生成
  - [x] 章节管理
  - [x] 队列任务系统
- [ ] 前端界面开发
  - [ ] 登录/注册页面
  - [ ] 课程列表页
  - [ ] 课程详情页
  - [ ] 章节编辑器
  - [ ] 用户中心
- [ ] 高级功能
  - [ ] Markdown编辑器（CodeMirror）
  - [ ] 导出功能（Word/PDF/HTML）
  - [ ] 版本对比
  - [ ] AI对话助手
  - [ ] 管理后台
- [ ] 优化与测试
  - [ ] 性能优化
  - [ ] 单元测试
  - [ ] 文档完善

## 🤝 贡献指南

欢迎提交Issue和Pull Request！

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 打开Pull Request

## 📝 许可证

本项目采用 Apache 2.0 许可证。详见 [LICENSE](LICENSE) 文件。

## 🙏 致谢

- [ThinkPHP](https://www.thinkphp.cn/) - 优秀的PHP框架
- [Google Gemini](https://deepmind.google/technologies/gemini/) - 强大的AI引擎
- [Parsedown](https://parsedown.org/) - Markdown解析器

## 📧 联系方式

- 项目地址：https://github.com/your-username/course-genius
- 问题反馈：https://github.com/your-username/course-genius/issues

---

**CourseGenius** - 让课程创作更简单 ✨
