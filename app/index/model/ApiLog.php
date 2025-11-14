<?php
declare(strict_types=1);

namespace app\index\model;

use think\Model;

/**
 * API调用日志模型
 */
class ApiLog extends Model
{
    protected $name = 'api_log';

    // 自动时间戳
    protected $autoWriteTimestamp = 'create_time';
    protected $updateTime = false;

    // 类型转换
    protected $type = [
        'user_id' => 'integer',
        'token_used' => 'integer',
        'status_code' => 'integer',
        'success' => 'integer',
        'duration' => 'integer',
    ];

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取成功率统计
     */
    public static function getSuccessRate(int $userId, int $days = 7): float
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $total = self::where('user_id', $userId)
            ->where('create_time', '>=', $startDate)
            ->count();

        if ($total == 0) {
            return 0;
        }

        $success = self::where('user_id', $userId)
            ->where('create_time', '>=', $startDate)
            ->where('success', 1)
            ->count();

        return round(($success / $total) * 100, 2);
    }
}
