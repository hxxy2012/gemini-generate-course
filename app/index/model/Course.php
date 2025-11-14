<?php
declare(strict_types=1);

namespace app\index\model;

use think\Model;
use think\model\concern\SoftDelete;

/**
 * 课程模型
 */
class Course extends Model
{
    use SoftDelete;

    protected $name = 'course';
    protected $deleteTime = 'delete_time';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'user_id' => 'int',
        'course_title' => 'string',
        'course_description' => 'string',
        'course_topic' => 'string',
        'target_audience' => 'string',
        'content_style' => 'string',
        'language' => 'string',
        'chapter_count_suggest' => 'int',
        'depth_level' => 'int',
        'word_count_per_chapter' => 'int',
        'include_code' => 'int',
        'include_exercises' => 'int',
        'special_requirements' => 'string',
        'status' => 'int',
        'outline_generated' => 'int',
        'total_chapters' => 'int',
        'generated_chapters' => 'int',
        'total_words' => 'int',
        'cover_image' => 'string',
        'tags' => 'string',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
        'delete_time' => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 类型转换
    protected $type = [
        'user_id' => 'integer',
        'chapter_count_suggest' => 'integer',
        'depth_level' => 'integer',
        'word_count_per_chapter' => 'integer',
        'include_code' => 'integer',
        'include_exercises' => 'integer',
        'status' => 'integer',
        'outline_generated' => 'integer',
        'total_chapters' => 'integer',
        'generated_chapters' => 'integer',
        'total_words' => 'integer',
    ];

    /**
     * 状态常量
     */
    const STATUS_DRAFT = 0;           // 草稿
    const STATUS_OUTLINE_GENERATING = 1; // 大纲生成中
    const STATUS_OUTLINE_COMPLETE = 2;   // 大纲完成
    const STATUS_CONTENT_GENERATING = 3; // 内容生成中
    const STATUS_COMPLETE = 4;          // 已完成

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            self::STATUS_DRAFT => '草稿',
            self::STATUS_OUTLINE_GENERATING => '大纲生成中',
            self::STATUS_OUTLINE_COMPLETE => '大纲完成',
            self::STATUS_CONTENT_GENERATING => '内容生成中',
            self::STATUS_COMPLETE => '已完成',
        ];
        return $statusMap[$data['status']] ?? '未知';
    }

    /**
     * 获取进度百分比
     */
    public function getProgressAttr($value, $data): int
    {
        if ($data['total_chapters'] == 0) {
            return 0;
        }
        return (int)(($data['generated_chapters'] / $data['total_chapters']) * 100);
    }

    /**
     * 获取目标受众文本
     */
    public function getTargetAudienceTextAttr($value, $data): string
    {
        $map = [
            'beginner' => '初学者',
            'intermediate' => '中级',
            'advanced' => '高级',
        ];
        return $map[$data['target_audience']] ?? $data['target_audience'];
    }

    /**
     * 获取内容风格文本
     */
    public function getContentStyleTextAttr($value, $data): string
    {
        $map = [
            'formal' => '正式/学术',
            'casual' => '通俗易懂',
            'humorous' => '幽默风趣',
            'practical' => '实战导向',
        ];
        return $map[$data['content_style']] ?? $data['content_style'];
    }

    /**
     * 关联用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联章节
     */
    public function chapters()
    {
        return $this->hasMany(Chapter::class);
    }

    /**
     * 获取根章节（一级章节）
     */
    public function rootChapters()
    {
        return $this->hasMany(Chapter::class)->where('parent_id', 0)->order('sort', 'asc');
    }

    /**
     * 更新统计数据
     */
    public function updateStats(): void
    {
        // 统计叶子节点（实际章节）数量
        $totalChapters = Chapter::where('course_id', $this->id)
            ->where('status', '>', 0)
            ->count();

        // 统计已生成章节数量
        $generatedChapters = Chapter::where('course_id', $this->id)
            ->where('status', Chapter::STATUS_GENERATED)
            ->count();

        // 统计总字数
        $totalWords = Chapter::where('course_id', $this->id)
            ->where('status', Chapter::STATUS_GENERATED)
            ->sum('word_count');

        $this->total_chapters = $totalChapters;
        $this->generated_chapters = $generatedChapters;
        $this->total_words = $totalWords;
        $this->save();
    }
}
