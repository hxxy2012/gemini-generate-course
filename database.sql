-- CourseGenius 数据库结构
-- MySQL 8.0+

CREATE DATABASE IF NOT EXISTS `course_genius` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `course_genius`;

-- 用户表
CREATE TABLE `cg_user` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE COMMENT '用户名',
  `email` VARCHAR(100) NOT NULL UNIQUE COMMENT '邮箱',
  `password` VARCHAR(255) NOT NULL COMMENT '密码',
  `nickname` VARCHAR(50) DEFAULT NULL COMMENT '昵称',
  `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像',
  `phone` VARCHAR(20) DEFAULT NULL COMMENT '手机号',
  `user_level` TINYINT DEFAULT 1 COMMENT '用户等级：1免费2基础3专业',
  `api_quota_daily` INT DEFAULT 100 COMMENT '每日API配额',
  `api_used_today` INT DEFAULT 0 COMMENT '今日已用配额',
  `quota_reset_time` DATETIME DEFAULT NULL COMMENT '配额重置时间',
  `status` TINYINT DEFAULT 1 COMMENT '状态：0禁用1启用',
  `last_login_time` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` VARCHAR(50) DEFAULT NULL COMMENT '最后登录IP',
  `create_time` DATETIME NOT NULL COMMENT '创建时间',
  `update_time` DATETIME DEFAULT NULL COMMENT '更新时间',
  INDEX `idx_username` (`username`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- 课程项目表
CREATE TABLE `cg_course` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `course_title` VARCHAR(255) NOT NULL COMMENT '课程标题',
  `course_description` TEXT DEFAULT NULL COMMENT '课程描述',
  `course_topic` TEXT NOT NULL COMMENT '课程主题（用于生成大纲）',
  `target_audience` VARCHAR(50) DEFAULT NULL COMMENT '目标受众：beginner/intermediate/advanced',
  `content_style` VARCHAR(50) DEFAULT NULL COMMENT '内容风格：formal/casual/humorous/practical',
  `language` VARCHAR(20) DEFAULT 'zh-CN' COMMENT '语言',
  `chapter_count_suggest` INT DEFAULT 10 COMMENT '建议章节数',
  `depth_level` TINYINT DEFAULT 3 COMMENT '目录级数：2/3/4/5',
  `word_count_per_chapter` INT DEFAULT 1500 COMMENT '每章建议字数',
  `include_code` TINYINT DEFAULT 0 COMMENT '包含代码示例：0否1是',
  `include_exercises` TINYINT DEFAULT 0 COMMENT '包含练习题：0否1是',
  `special_requirements` TEXT DEFAULT NULL COMMENT '特殊要求',
  `status` TINYINT DEFAULT 0 COMMENT '状态：0草稿1大纲生成中2大纲完成3内容生成中4已完成',
  `outline_generated` TINYINT DEFAULT 0 COMMENT '大纲是否已生成：0否1是',
  `total_chapters` INT DEFAULT 0 COMMENT '总章节数（叶子节点）',
  `generated_chapters` INT DEFAULT 0 COMMENT '已生成章节数',
  `total_words` INT DEFAULT 0 COMMENT '总字数',
  `cover_image` VARCHAR(255) DEFAULT NULL COMMENT '封面图',
  `tags` VARCHAR(255) DEFAULT NULL COMMENT '标签（逗号分隔）',
  `create_time` DATETIME NOT NULL COMMENT '创建时间',
  `update_time` DATETIME DEFAULT NULL COMMENT '更新时间',
  `delete_time` DATETIME DEFAULT NULL COMMENT '删除时间（软删除）',
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='课程项目表';

-- 课程章节表（树形结构）
CREATE TABLE `cg_chapter` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT UNSIGNED NOT NULL COMMENT '课程ID',
  `parent_id` INT UNSIGNED DEFAULT 0 COMMENT '父级ID，0为根节点',
  `chapter_number` VARCHAR(50) NOT NULL COMMENT '章节编号（如1.1.1）',
  `chapter_title` VARCHAR(255) NOT NULL COMMENT '章节标题',
  `chapter_description` TEXT DEFAULT NULL COMMENT '章节描述',
  `content` LONGTEXT DEFAULT NULL COMMENT '章节内容（Markdown）',
  `content_html` LONGTEXT DEFAULT NULL COMMENT '章节内容（HTML，渲染后）',
  `word_count` INT DEFAULT 0 COMMENT '字数',
  `level` TINYINT NOT NULL COMMENT '层级：1一级2二级3三级...',
  `sort` INT DEFAULT 0 COMMENT '排序',
  `status` TINYINT DEFAULT 0 COMMENT '生成状态：0未生成1生成中2已生成3生成失败',
  `generate_prompt` TEXT DEFAULT NULL COMMENT '生成时使用的Prompt（用于重新生成）',
  `generate_params` TEXT DEFAULT NULL COMMENT '生成参数（JSON）',
  `ai_model` VARCHAR(50) DEFAULT NULL COMMENT '使用的AI模型',
  `token_used` INT DEFAULT 0 COMMENT '消耗的Token数',
  `generate_time` DATETIME DEFAULT NULL COMMENT '生成时间',
  `error_message` TEXT DEFAULT NULL COMMENT '错误信息（生成失败时）',
  `create_time` DATETIME NOT NULL COMMENT '创建时间',
  `update_time` DATETIME DEFAULT NULL COMMENT '更新时间',
  INDEX `idx_course_id` (`course_id`),
  INDEX `idx_parent_id` (`parent_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='课程章节表';

-- 章节版本历史表
CREATE TABLE `cg_chapter_version` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `chapter_id` INT UNSIGNED NOT NULL COMMENT '章节ID',
  `content` LONGTEXT NOT NULL COMMENT '版本内容',
  `word_count` INT DEFAULT 0 COMMENT '字数',
  `version_number` INT NOT NULL COMMENT '版本号',
  `change_description` VARCHAR(255) DEFAULT NULL COMMENT '修改说明',
  `create_time` DATETIME NOT NULL COMMENT '创建时间',
  INDEX `idx_chapter_id` (`chapter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='章节版本历史表';

-- 生成任务队列表
CREATE TABLE `cg_generate_task` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `task_type` VARCHAR(50) NOT NULL COMMENT '任务类型：outline大纲/content内容',
  `course_id` INT UNSIGNED NOT NULL COMMENT '课程ID',
  `chapter_id` INT UNSIGNED DEFAULT NULL COMMENT '章节ID（内容生成时）',
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `prompt` TEXT NOT NULL COMMENT '生成Prompt',
  `params` TEXT DEFAULT NULL COMMENT '生成参数（JSON）',
  `status` TINYINT DEFAULT 0 COMMENT '状态：0待处理1处理中2已完成3失败',
  `priority` TINYINT DEFAULT 5 COMMENT '优先级：1-10',
  `result` LONGTEXT DEFAULT NULL COMMENT '生成结果',
  `error_message` TEXT DEFAULT NULL COMMENT '错误信息',
  `token_used` INT DEFAULT 0 COMMENT '消耗Token',
  `start_time` DATETIME DEFAULT NULL COMMENT '开始时间',
  `finish_time` DATETIME DEFAULT NULL COMMENT '完成时间',
  `retry_count` TINYINT DEFAULT 0 COMMENT '重试次数',
  `create_time` DATETIME NOT NULL COMMENT '创建时间',
  INDEX `idx_status` (`status`),
  INDEX `idx_course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='生成任务队列表';

-- 导出任务表
CREATE TABLE `cg_export_task` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `course_id` INT UNSIGNED NOT NULL COMMENT '课程ID',
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `export_format` VARCHAR(20) NOT NULL COMMENT '导出格式：docx/pdf/md/html/epub',
  `export_settings` TEXT DEFAULT NULL COMMENT '导出设置（JSON）',
  `status` TINYINT DEFAULT 0 COMMENT '状态：0待处理1处理中2已完成3失败',
  `file_path` VARCHAR(255) DEFAULT NULL COMMENT '文件路径',
  `file_size` INT DEFAULT 0 COMMENT '文件大小（字节）',
  `download_url` VARCHAR(255) DEFAULT NULL COMMENT '下载链接',
  `expire_time` DATETIME DEFAULT NULL COMMENT '过期时间（24小时）',
  `error_message` TEXT DEFAULT NULL COMMENT '错误信息',
  `create_time` DATETIME NOT NULL COMMENT '创建时间',
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='导出任务表';

-- API调用日志表
CREATE TABLE `cg_api_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL COMMENT '用户ID',
  `api_type` VARCHAR(50) NOT NULL COMMENT 'API类型：gemini',
  `endpoint` VARCHAR(255) DEFAULT NULL COMMENT 'API端点',
  `request_data` TEXT DEFAULT NULL COMMENT '请求数据',
  `response_data` TEXT DEFAULT NULL COMMENT '响应数据',
  `token_used` INT DEFAULT 0 COMMENT '消耗Token',
  `status_code` INT DEFAULT NULL COMMENT 'HTTP状态码',
  `success` TINYINT DEFAULT 1 COMMENT '是否成功：0失败1成功',
  `error_message` TEXT DEFAULT NULL COMMENT '错误信息',
  `duration` INT DEFAULT 0 COMMENT '耗时（毫秒）',
  `ip_address` VARCHAR(50) DEFAULT NULL COMMENT 'IP地址',
  `create_time` DATETIME NOT NULL COMMENT '创建时间',
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_create_time` (`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='API调用日志表';

-- 系统配置表
CREATE TABLE `cg_config` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `config_key` VARCHAR(100) NOT NULL UNIQUE COMMENT '配置键',
  `config_value` TEXT DEFAULT NULL COMMENT '配置值',
  `config_type` VARCHAR(20) DEFAULT 'string' COMMENT '配置类型：string/number/json/boolean',
  `config_group` VARCHAR(50) DEFAULT NULL COMMENT '配置分组',
  `description` VARCHAR(255) DEFAULT NULL COMMENT '描述',
  `sort` INT DEFAULT 0 COMMENT '排序',
  `create_time` DATETIME NOT NULL COMMENT '创建时间',
  `update_time` DATETIME DEFAULT NULL COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- 初始化配置数据
INSERT INTO `cg_config` (`config_key`, `config_value`, `config_group`, `description`, `create_time`) VALUES
('gemini_api_key', '', 'api', 'Gemini API Key', NOW()),
('gemini_api_model', 'gemini-pro', 'api', 'Gemini模型名称', NOW()),
('gemini_api_endpoint', 'https://generativelanguage.googleapis.com/v1beta/models/', 'api', 'Gemini API端点', NOW()),
('default_word_count', '1500', 'generation', '默认每章字数', NOW()),
('default_content_style', 'practical', 'generation', '默认内容风格', NOW()),
('max_chapters_free', '10', 'quota', '免费版最大章节数', NOW()),
('max_chapters_basic', '50', 'quota', '基础版最大章节数', NOW()),
('api_quota_daily_free', '50', 'quota', '免费版每日API配额', NOW()),
('api_quota_daily_basic', '200', 'quota', '基础版每日API配额', NOW()),
('export_expire_hours', '24', 'export', '导出文件过期时间（小时）', NOW());

-- 创建默认管理员账户（密码：admin123）
INSERT INTO `cg_user` (`username`, `email`, `password`, `nickname`, `user_level`, `api_quota_daily`, `status`, `create_time`) VALUES
('admin', 'admin@coursegenius.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '管理员', 3, 1000, 1, NOW());
