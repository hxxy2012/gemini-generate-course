<?php
declare(strict_types=1);

namespace app\index\model;

use think\Model;
use Parsedown;

/**
 * 章节模型
 */
class Chapter extends Model
{
    protected $name = 'chapter';

    // 设置字段信息
    protected $schema = [
        'id' => 'int',
        'course_id' => 'int',
        'parent_id' => 'int',
        'chapter_number' => 'string',
        'chapter_title' => 'string',
        'chapter_description' => 'string',
        'content' => 'string',
        'content_html' => 'string',
        'word_count' => 'int',
        'level' => 'int',
        'sort' => 'int',
        'status' => 'int',
        'generate_prompt' => 'string',
        'generate_params' => 'string',
        'ai_model' => 'string',
        'token_used' => 'int',
        'generate_time' => 'datetime',
        'error_message' => 'string',
        'create_time' => 'datetime',
        'update_time' => 'datetime',
    ];

    // 自动时间戳
    protected $autoWriteTimestamp = true;

    // 类型转换
    protected $type = [
        'course_id' => 'integer',
        'parent_id' => 'integer',
        'word_count' => 'integer',
        'level' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
        'token_used' => 'integer',
    ];

    /**
     * 状态常量
     */
    const STATUS_NOT_GENERATED = 0;  // 未生成
    const STATUS_GENERATING = 1;     // 生成中
    const STATUS_GENERATED = 2;      // 已生成
    const STATUS_FAILED = 3;         // 生成失败

    /**
     * 获取状态文本
     */
    public function getStatusTextAttr($value, $data): string
    {
        $statusMap = [
            self::STATUS_NOT_GENERATED => '未生成',
            self::STATUS_GENERATING => '生成中',
            self::STATUS_GENERATED => '已生成',
            self::STATUS_FAILED => '生成失败',
        ];
        return $statusMap[$data['status']] ?? '未知';
    }

    /**
     * 内容修改器 - 自动转换HTML
     */
    public function setContentAttr($value): string
    {
        if ($value) {
            $parsedown = new Parsedown();
            $this->set('content_html', $parsedown->text($value));
            $this->set('word_count', mb_strlen(strip_tags($value)));
        }
        return $value;
    }

    /**
     * 关联课程
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * 关联父章节
     */
    public function parent()
    {
        return $this->belongsTo(Chapter::class, 'parent_id');
    }

    /**
     * 关联子章节
     */
    public function children()
    {
        return $this->hasMany(Chapter::class, 'parent_id')->order('sort', 'asc');
    }

    /**
     * 获取所有子孙章节ID
     */
    public function getDescendantIds(): array
    {
        $ids = [];
        $children = $this->children()->select();
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getDescendantIds());
        }
        return $ids;
    }

    /**
     * 获取章节完整路径
     */
    public function getFullPath(): string
    {
        $path = [$this->chapter_title];
        $parent = $this->parent;
        while ($parent) {
            array_unshift($path, $parent->chapter_title);
            $parent = $parent->parent;
        }
        return implode(' > ', $path);
    }

    /**
     * 是否是叶子节点
     */
    public function isLeaf(): bool
    {
        return $this->children()->count() == 0;
    }

    /**
     * 保存版本历史
     */
    public function saveVersion(string $changeDescription = ''): void
    {
        if (!$this->content) {
            return;
        }

        // 获取最新版本号
        $latestVersion = ChapterVersion::where('chapter_id', $this->id)
            ->order('version_number', 'desc')
            ->value('version_number') ?? 0;

        ChapterVersion::create([
            'chapter_id' => $this->id,
            'content' => $this->content,
            'word_count' => $this->word_count,
            'version_number' => $latestVersion + 1,
            'change_description' => $changeDescription,
            'create_time' => date('Y-m-d H:i:s'),
        ]);
    }
}
