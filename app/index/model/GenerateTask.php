<?php
declare(strict_types=1);

namespace app\index\model;

use think\Model;

/**
 * 生成任务模型
 */
class GenerateTask extends Model
{
    protected $name = 'generate_task';

    // 自动时间戳
    protected $autoWriteTimestamp = 'create_time';
    protected $updateTime = false;

    // 类型转换
    protected $type = [
        'course_id' => 'integer',
        'chapter_id' => 'integer',
        'user_id' => 'integer',
        'status' => 'integer',
        'priority' => 'integer',
        'token_used' => 'integer',
        'retry_count' => 'integer',
    ];

    /**
     * 任务类型常量
     */
    const TYPE_OUTLINE = 'outline';  // 大纲生成
    const TYPE_CONTENT = 'content';  // 内容生成

    /**
     * 状态常量
     */
    const STATUS_PENDING = 0;     // 待处理
    const STATUS_PROCESSING = 1;  // 处理中
    const STATUS_COMPLETED = 2;   // 已完成
    const STATUS_FAILED = 3;      // 失败

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_FAILED => '失败',
        ];
        return $statusMap[$data['status']] ?? '未知';
    }

    /**
     * 关联课程
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * 关联章节
     */
    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
