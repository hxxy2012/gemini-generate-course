<?php
declare(strict_types=1);

namespace app\index\model;

use think\Model;

/**
 * 用户模型
 */
class User extends Model
{
    protected $name = 'user';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'username' => 'string',
        'email' => 'string',
        'password' => 'string',
        'nickname' => 'string',
        'avatar' => 'string',
        'phone' => 'string',
        'user_level' => 'int',
        'api_quota_daily' => 'int',
        'api_used_today' => 'int',
        'quota_reset_time' => 'datetime',
        'status' => 'int',
        'last_login_time' => 'datetime',
        'last_login_ip' => 'string',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 只读字段
    protected $readonly = ['id', 'username', 'create_time'];

    // 隐藏字段
    protected $hidden = ['password'];

    /**
     * 密码修改器
     */
    public function setPasswordAttr($value): string
    {
        return password_hash($value, PASSWORD_DEFAULT);
    }

    /**
     * 验证密码
     */
    public function checkPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    /**
     * 检查并重置API配额
     */
    public function checkAndResetQuota(): void
    {
        if ($this->quota_reset_time && strtotime($this->quota_reset_time) < time()) {
            $this->api_used_today = 0;
            $this->quota_reset_time = date('Y-m-d H:i:s', strtotime('+1 day'));
            $this->save();
        }
    }

    /**
     * 检查API配额是否充足
     */
    public function hasQuota(): bool
    {
        $this->checkAndResetQuota();
        return $this->api_used_today < $this->api_quota_daily;
    }

    /**
     * 消耗API配额
     */
    public function consumeQuota(int $count = 1): bool
    {
        if (!$this->hasQuota()) {
            return false;
        }
        $this->api_used_today += $count;
        return $this->save();
    }

    /**
     * 获取用户等级名称
     */
    public function getLevelNameAttr($value, $data): string
    {
        $levels = [
            1 => '免费版',
            2 => '基础版',
            3 => '专业版',
        ];
        return $levels[$data['user_level']] ?? '未知';
    }

    /**
     * 关联课程
     */
    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}
