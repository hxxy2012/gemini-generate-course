<?php

// +----------------------------------------------------------------------
// | Gemini API 配置
// +----------------------------------------------------------------------

return [
    // API Key（从环境变量读取，或者从数据库配置表读取）
    'api_key' => env('gemini.api_key', ''),

    // API 端点
    'api_endpoint' => 'https://generativelanguage.googleapis.com/v1beta/models/',

    // 默认模型
    'model' => env('gemini.model', 'gemini-pro'),

    // 超时时间（秒）
    'timeout' => 120,

    // 大纲生成默认参数
    'outline_generation' => [
        'temperature' => 0.7,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 4096,
    ],

    // 内容生成默认参数
    'content_generation' => [
        'temperature' => 0.8,
        'topK' => 40,
        'topP' => 0.95,
        'maxOutputTokens' => 8192,
    ],

    // 重试配置
    'retry' => [
        'max_attempts' => 3,
        'delay' => 2000, // 毫秒
    ],
];
