<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\BaseController;
use app\index\model\User;
use think\facade\Session;

/**
 * 用户认证控制器
 */
class Auth extends BaseController
{
    /**
     * 登录页面
     */
    public function login()
    {
        if ($this->request->isPost()) {
            $username = $this->request->post('username');
            $password = $this->request->post('password');
            $remember = $this->request->post('remember', 0);

            if (empty($username) || empty($password)) {
                return $this->error('用户名和密码不能为空');
            }

            // 查找用户（支持用户名或邮箱登录）
            $user = User::where('username', $username)
                ->whereOr('email', $username)
                ->find();

            if (!$user) {
                return $this->error('用户不存在');
            }

            if ($user->status != 1) {
                return $this->error('账户已被禁用');
            }

            if (!$user->checkPassword($password)) {
                return $this->error('密码错误');
            }

            // 登录成功，设置session
            Session::set('user_id', $user->id);
            Session::set('username', $user->username);
            Session::set('user_level', $user->user_level);

            // 记住登录
            if ($remember) {
                cookie('remember_token', md5($user->id . $user->password), 7 * 24 * 3600);
            }

            // 更新登录信息
            $user->last_login_time = date('Y-m-d H:i:s');
            $user->last_login_ip = $this->request->ip();
            $user->save();

            return $this->success([
                'user_id' => $user->id,
                'username' => $user->username,
            ], '登录成功');
        }

        return view('login');
    }

    /**
     * 注册
     */
    public function register()
    {
        if ($this->request->isPost()) {
            $data = $this->request->post();

            // 验证数据
            $validate = [
                'username' => 'require|alphaDash|length:3,50|unique:user',
                'email' => 'require|email|unique:user',
                'password' => 'require|length:6,20',
                'password_confirm' => 'require|confirm:password',
            ];

            $message = [
                'username.require' => '用户名不能为空',
                'username.alphaDash' => '用户名只能包含字母、数字、下划线和破折号',
                'username.length' => '用户名长度必须在3-50个字符之间',
                'username.unique' => '用户名已存在',
                'email.require' => '邮箱不能为空',
                'email.email' => '邮箱格式不正确',
                'email.unique' => '邮箱已被注册',
                'password.require' => '密码不能为空',
                'password.length' => '密码长度必须在6-20个字符之间',
                'password_confirm.require' => '确认密码不能为空',
                'password_confirm.confirm' => '两次密码不一致',
            ];

            try {
                $this->validate($data, $validate, $message);
            } catch (\Exception $e) {
                return $this->error($e->getMessage());
            }

            // 创建用户
            try {
                $user = User::create([
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'nickname' => $data['username'],
                    'user_level' => 1, // 默认免费版
                    'api_quota_daily' => 50, // 免费版每日配额
                    'quota_reset_time' => date('Y-m-d H:i:s', strtotime('+1 day')),
                    'status' => 1,
                    'create_time' => date('Y-m-d H:i:s'),
                ]);

                return $this->success([
                    'user_id' => $user->id,
                ], '注册成功');
            } catch (\Exception $e) {
                return $this->error('注册失败: ' . $e->getMessage());
            }
        }

        return view('register');
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        Session::clear();
        cookie('remember_token', null);

        if ($this->request->isAjax()) {
            return $this->success([], '退出成功');
        }

        return redirect('/login');
    }

    /**
     * 获取当前用户信息
     */
    public function userInfo()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $user = User::find($userId);
        if (!$user) {
            return $this->error('用户不存在');
        }

        $user->append(['level_name']);
        $user->hidden(['password']);

        return $this->success($user->toArray());
    }

    /**
     * 修改密码
     */
    public function changePassword()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        if ($this->request->isPost()) {
            $oldPassword = $this->request->post('old_password');
            $newPassword = $this->request->post('new_password');
            $confirmPassword = $this->request->post('confirm_password');

            if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
                return $this->error('请填写完整信息');
            }

            if ($newPassword !== $confirmPassword) {
                return $this->error('两次密码不一致');
            }

            if (strlen($newPassword) < 6) {
                return $this->error('新密码长度不能少于6位');
            }

            $user = User::find($userId);
            if (!$user->checkPassword($oldPassword)) {
                return $this->error('原密码错误');
            }

            $user->password = $newPassword;
            $user->save();

            return $this->success([], '密码修改成功');
        }

        return view('change_password');
    }
}
