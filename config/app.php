<?php
// +----------------------------------------------------------------------
// | 应用设置
// +----------------------------------------------------------------------

return [
    // 应用名称
    'app_name' => 'CourseGenius',

    // 应用地址
    'app_host' => '',

    // 应用调试模式
    'app_debug' => true,

    // 应用Trace
    'app_trace' => false,

    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 默认语言
    'default_lang' => 'zh-cn',

    // 应用映射（自动多应用模式）
    'app_map' => [],

    // 域名绑定（自动多应用模式）
    'domain_bind' => [],

    // 禁止URL访问的应用列表（自动多应用模式）
    'deny_app_list' => ['common'],

    // 异常页面的模板文件
    'exception_tmpl' => app()->getThinkPath() . 'tpl/think_exception.tpl',

    // 错误显示信息,非调试模式有效
    'error_message' => '页面错误！请稍后再试～',

    // 显示错误信息
    'show_error_msg' => false,
];
