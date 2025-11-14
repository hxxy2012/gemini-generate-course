<?php
declare(strict_types=1);

namespace app\index\model;

use think\Model;

/**
 * 章节版本历史模型
 */
class ChapterVersion extends Model
{
    protected $name = 'chapter_version';

    // 自动时间戳
    protected $autoWriteTimestamp = 'create_time';
    protected $updateTime = false;

    // 类型转换
    protected $type = [
        'chapter_id' => 'integer',
        'word_count' => 'integer',
        'version_number' => 'integer',
    ];

    /**
     * 关联章节
     */
    public function chapter()
    {
        return $this->belongsTo(Chapter::class);
    }
}
