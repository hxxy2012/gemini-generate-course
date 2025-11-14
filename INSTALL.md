# CourseGenius 安装部署指南

## 目录

- [环境准备](#环境准备)
- [安装步骤](#安装步骤)
- [配置说明](#配置说明)
- [队列配置](#队列配置)
- [Web服务器配置](#web服务器配置)
- [常见问题](#常见问题)

## 环境准备

### 系统要求

- **操作系统**：Linux / macOS / Windows
- **PHP版本**：>= 8.0
- **MySQL版本**：>= 8.0
- **Web服务器**：Apache / Nginx
- **其他要求**：
  - Composer
  - PHP扩展：PDO、PDO_MySQL、mbstring、json、curl、openssl
  - 可选：Redis（用于缓存和队列）

### 获取 Gemini API Key

1. 访问 [Google AI Studio](https://makersuite.google.com/app/apikey)
2. 登录 Google 账户
3. 点击「Create API Key」
4. 复制生成的 API Key

> **注意**：Gemini API 目前在某些地区可能需要使用代理访问。

## 安装步骤

### 1. 克隆项目

```bash
# 使用 Git 克隆
git clone https://github.com/your-username/course-genius.git
cd course-genius

# 或者下载 ZIP 包解压
```

### 2. 安装 Composer 依赖

```bash
composer install
```

如果遇到速度慢的问题，可以使用国内镜像：

```bash
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
composer install
```

### 3. 配置环境变量

```bash
# 复制环境配置文件
cp .env.example .env

# 编辑配置文件
nano .env  # 或使用其他编辑器
```

编辑 `.env` 文件内容：

```ini
# 应用配置
APP_DEBUG = true

# 数据库配置
[DATABASE]
TYPE = mysql
HOSTNAME = 127.0.0.1
DATABASE = course_genius
USERNAME = root
PASSWORD = your_mysql_password
HOSTPORT = 3306
CHARSET = utf8mb4
PREFIX = cg_

# Redis配置（可选）
[REDIS]
HOST = 127.0.0.1
PORT = 6379
PASSWORD =

# Gemini API配置
[GEMINI]
API_KEY = your_gemini_api_key_here
MODEL = gemini-pro
```

### 4. 创建数据库

```bash
# 登录 MySQL
mysql -u root -p

# 创建数据库
CREATE DATABASE course_genius DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;

# 导入数据库结构
mysql -u root -p course_genius < database.sql
```

或者使用 phpMyAdmin 等工具导入 `database.sql` 文件。

### 5. 设置目录权限

```bash
# Linux/macOS
chmod -R 755 runtime
chmod -R 755 public/uploads

# 确保 Web 服务器用户有写入权限
chown -R www-data:www-data runtime
chown -R www-data:www-data public/uploads
```

### 6. 测试安装

访问 `http://your-domain.com/health` 检查系统状态。

应该看到类似以下的响应：

```json
{
  "status": "healthy",
  "database": "ok",
  "time": 1234567890
}
```

## 配置说明

### 数据库配置

编辑 `config/database.php` 或 `.env` 文件中的数据库配置。

**生产环境建议**：
- 使用独立的数据库用户，不要使用 root
- 设置强密码
- 限制数据库用户的访问IP

### Gemini API 配置

编辑 `config/gemini.php` 自定义生成参数：

```php
return [
    'api_key' => env('gemini.api_key', ''),
    'model' => env('gemini.model', 'gemini-pro'),
    'timeout' => 120, // API超时时间（秒）

    // 大纲生成参数
    'outline_generation' => [
        'temperature' => 0.7,  // 创造性，范围0-1
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 4096,
    ],

    // 内容生成参数
    'content_generation' => [
        'temperature' => 0.8,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 8192,
    ],
];
```

**参数说明**：
- `temperature`：控制输出的随机性，0表示确定性输出，1表示高创造性
- `topK`：每步采样的候选词数量
- `topP`：累积概率阈值
- `maxOutputTokens`：最大输出长度

### 用户套餐配置

在数据库 `cg_config` 表中修改配置：

```sql
-- 查看当前配置
SELECT * FROM cg_config WHERE config_group = 'quota';

-- 修改免费版配额
UPDATE cg_config SET config_value = '100' WHERE config_key = 'api_quota_daily_free';

-- 修改基础版配额
UPDATE cg_config SET config_value = '500' WHERE config_key = 'api_quota_daily_basic';
```

## 队列配置

CourseGenius 使用队列处理AI生成任务，必须启动队列处理器。

### 方式一：手动启动（测试）

```bash
php think queue:work
```

### 方式二：使用 Supervisor（推荐）

#### 安装 Supervisor

```bash
# Ubuntu/Debian
sudo apt-get install supervisor

# CentOS/RHEL
sudo yum install supervisor
```

#### 创建配置文件

创建 `/etc/supervisor/conf.d/course-genius.conf`：

```ini
[program:course-genius-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/course-genius/think queue:work
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/course-genius/runtime/log/queue.log
stdout_logfile_maxbytes=50MB
stdout_logfile_backups=10
```

**注意**：
- 修改 `/path/to/course-genius` 为实际路径
- `user=www-data` 修改为你的Web服务器用户
- `numprocs=2` 表示启动2个进程，可根据服务器性能调整

#### 启动队列

```bash
# 重新读取配置
sudo supervisorctl reread

# 更新配置
sudo supervisorctl update

# 启动队列
sudo supervisorctl start course-genius-queue:*

# 查看状态
sudo supervisorctl status

# 重启队列
sudo supervisorctl restart course-genius-queue:*

# 停止队列
sudo supervisorctl stop course-genius-queue:*
```

#### 查看日志

```bash
tail -f /path/to/course-genius/runtime/log/queue.log
```

### 方式三：使用 systemd

创建 `/etc/systemd/system/course-genius-queue.service`：

```ini
[Unit]
Description=CourseGenius Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/path/to/course-genius
ExecStart=/usr/bin/php /path/to/course-genius/think queue:work
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

启动服务：

```bash
sudo systemctl daemon-reload
sudo systemctl enable course-genius-queue
sudo systemctl start course-genius-queue
sudo systemctl status course-genius-queue
```

## Web服务器配置

### Apache 配置

#### 1. 启用 mod_rewrite

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### 2. 配置虚拟主机

编辑 `/etc/apache2/sites-available/course-genius.conf`：

```apache
<VirtualHost *:80>
    ServerName course-genius.com
    ServerAlias www.course-genius.com
    DocumentRoot /path/to/course-genius/public

    <Directory /path/to/course-genius/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/course-genius-error.log
    CustomLog ${APACHE_LOG_DIR}/course-genius-access.log combined
</VirtualHost>
```

启用站点：

```bash
sudo a2ensite course-genius
sudo systemctl reload apache2
```

### Nginx 配置

编辑 `/etc/nginx/sites-available/course-genius.conf`：

```nginx
server {
    listen 80;
    server_name course-genius.com www.course-genius.com;
    root /path/to/course-genius/public;
    index index.php index.html;

    # 日志
    access_log /var/log/nginx/course-genius-access.log;
    error_log /var/log/nginx/course-genius-error.log;

    # 防止访问隐藏文件
    location ~ /\. {
        deny all;
    }

    # 静态文件缓存
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # ThinkPHP 路由
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP 处理
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # 上传大小限制
        client_max_body_size 50M;
    }
}
```

启用站点：

```bash
sudo ln -s /etc/nginx/sites-available/course-genius.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### HTTPS 配置（Let's Encrypt）

```bash
# 安装 Certbot
sudo apt-get install certbot python3-certbot-nginx  # Nginx
# 或
sudo apt-get install certbot python3-certbot-apache  # Apache

# 获取证书
sudo certbot --nginx -d course-genius.com -d www.course-genius.com  # Nginx
# 或
sudo certbot --apache -d course-genius.com -d www.course-genius.com  # Apache

# 自动续期
sudo certbot renew --dry-run
```

## 常见问题

### 1. 数据库连接失败

**错误信息**：`SQLSTATE[HY000] [2002] Connection refused`

**解决方案**：
- 检查 MySQL 是否启动：`sudo systemctl status mysql`
- 检查 `.env` 中的数据库配置是否正确
- 检查防火墙设置

### 2. Gemini API 调用失败

**错误信息**：`API请求失败: Could not resolve host`

**解决方案**：
- 检查网络连接
- 检查 API Key 是否正确
- 某些地区可能需要配置代理
- 检查 PHP curl 扩展是否安装

### 3. 队列不工作

**问题**：生成任务一直显示"待处理"

**解决方案**：
- 检查队列进程是否运行：`sudo supervisorctl status`
- 查看队列日志：`tail -f runtime/log/queue.log`
- 手动运行测试：`php think queue:work`

### 4. 权限问题

**错误信息**：`Permission denied`

**解决方案**：
```bash
# 给予写入权限
sudo chown -R www-data:www-data runtime
sudo chown -R www-data:www-data public/uploads
sudo chmod -R 755 runtime
sudo chmod -R 755 public/uploads
```

### 5. Composer 安装慢

**解决方案**：
```bash
# 使用国内镜像
composer config -g repo.packagist composer https://mirrors.aliyun.com/composer/
composer install
```

### 6. PHP版本不兼容

**错误信息**：`Parse error: syntax error`

**解决方案**：
- 确保 PHP 版本 >= 8.0
- 检查版本：`php -v`
- 更新 PHP：参考操作系统文档

## 性能优化

### 1. 开启 OPcache

编辑 `php.ini`：

```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=60
```

### 2. 使用 Redis 缓存

编辑 `.env`：

```ini
[CACHE]
DRIVER = redis
```

### 3. 数据库优化

```sql
-- 添加索引
ALTER TABLE cg_course ADD INDEX idx_user_status (user_id, status);
ALTER TABLE cg_chapter ADD INDEX idx_course_parent (course_id, parent_id);
ALTER TABLE cg_generate_task ADD INDEX idx_status_priority (status, priority);
```

### 4. Nginx 优化

```nginx
# 启用 gzip 压缩
gzip on;
gzip_vary on;
gzip_min_length 1024;
gzip_types text/plain text/css text/xml text/javascript application/json application/javascript;

# 连接优化
keepalive_timeout 65;
client_max_body_size 50M;
```

## 生产环境检查清单

- [ ] 关闭调试模式：`APP_DEBUG = false`
- [ ] 配置 HTTPS
- [ ] 设置强数据库密码
- [ ] 限制数据库访问IP
- [ ] 启用防火墙
- [ ] 配置日志轮转
- [ ] 启用 OPcache
- [ ] 配置定期备份
- [ ] 监控队列状态
- [ ] 配置错误通知

## 更新升级

```bash
# 备份数据库
mysqldump -u root -p course_genius > backup.sql

# 备份文件
cp -r /path/to/course-genius /path/to/backup

# 拉取最新代码
git pull origin main

# 更新依赖
composer install

# 重启队列
sudo supervisorctl restart course-genius-queue:*

# 清除缓存
php think clear
```

## 支持与帮助

- 项目地址：https://github.com/your-username/course-genius
- 问题反馈：https://github.com/your-username/course-genius/issues
- 文档：查看 README.md

---

安装过程中遇到问题？欢迎提交 Issue！
