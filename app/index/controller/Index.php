<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\BaseController;
use app\index\service\CourseService;

/**
 * 首页控制器
 */
class Index extends BaseController
{
    /**
     * 首页
     */
    public function index()
    {
        $userId = $this->getUserId();

        // 如果未登录，跳转到登录页
        if (!$userId) {
            return redirect('/login');
        }

        // 已登录，跳转到课程列表
        return redirect('/course/index');
    }

    /**
     * 系统信息（用于测试）
     */
    public function info()
    {
        return json([
            'app_name' => 'CourseGenius',
            'version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'thinkphp_version' => app()->version(),
            'system_time' => date('Y-m-d H:i:s'),
            'status' => 'running',
        ]);
    }

    /**
     * 健康检查
     */
    public function health()
    {
        try {
            // 检查数据库连接
            \think\facade\Db::query('SELECT 1');
            $dbStatus = 'ok';
        } catch (\Exception $e) {
            $dbStatus = 'error: ' . $e->getMessage();
        }

        return json([
            'status' => 'healthy',
            'database' => $dbStatus,
            'time' => time(),
        ]);
    }
}
