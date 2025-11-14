<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\BaseController;
use app\index\model\User as UserModel;
use app\index\service\CourseService;

/**
 * 用户中心控制器
 */
class User extends BaseController
{
    protected $courseService;

    protected function initialize()
    {
        parent::initialize();
        $this->courseService = new CourseService();
    }

    /**
     * 用户中心首页
     */
    public function index()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return redirect('/login');
        }

        $user = UserModel::find($userId);
        $user->append(['level_name']);

        // 获取统计数据
        $stats = $this->courseService->getUserStats($userId);

        return view('user/index', [
            'user' => $user,
            'stats' => $stats['data'] ?? []
        ]);
    }

    /**
     * 个人资料
     */
    public function profile()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return redirect('/login');
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            $user = UserModel::find($userId);

            $user->nickname = $data['nickname'] ?? $user->nickname;
            $user->save();

            return $this->success([], '更新成功');
        }

        $user = UserModel::find($userId);
        return view('user/profile', ['user' => $user]);
    }

    /**
     * 账户设置
     */
    public function settings()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return redirect('/login');
        }

        $user = UserModel::find($userId);
        $user->append(['level_name']);

        return view('user/settings', ['user' => $user]);
    }

    /**
     * 统计数据
     */
    public function stats()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $stats = $this->courseService->getUserStats($userId);

        if ($stats['success']) {
            return view('user/stats', ['stats' => $stats['data']]);
        }

        return $this->error($stats['message']);
    }
}
