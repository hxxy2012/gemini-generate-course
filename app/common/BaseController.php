<?php
declare(strict_types=1);

namespace app\common;

use think\App;
use think\exception\ValidateException;
use think\Validate;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 当前登录用户ID
     * @var int
     */
    protected $userId = 0;

    /**
     * 当前登录用户信息
     * @var array
     */
    protected $user = [];

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    /**
     * 初始化
     */
    protected function initialize()
    {
        // 子类可覆盖此方法
    }

    /**
     * 成功响应
     */
    protected function success($data = [], string $message = '操作成功', int $code = 1)
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'time' => time(),
        ]);
    }

    /**
     * 失败响应
     */
    protected function error(string $message = '操作失败', int $code = 0, $data = [])
    {
        return json([
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'time' => time(),
        ]);
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v     = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->request->has('batch')) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }

    /**
     * 获取当前登录用户ID
     */
    protected function getUserId(): int
    {
        if (!$this->userId) {
            $this->userId = (int)session('user_id', 0);
        }
        return $this->userId;
    }

    /**
     * 检查登录状态
     */
    protected function checkLogin(): bool
    {
        return $this->getUserId() > 0;
    }

    /**
     * 要求登录
     */
    protected function requireLogin()
    {
        if (!$this->checkLogin()) {
            if ($this->request->isAjax()) {
                return $this->error('请先登录', 401);
            } else {
                return redirect('/login');
            }
        }
    }
}
