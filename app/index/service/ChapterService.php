<?php
declare(strict_types=1);

namespace app\index\service;

use app\index\model\Chapter;
use app\index\model\Course;

/**
 * 章节服务类
 */
class ChapterService
{
    /**
     * 获取课程章节树形结构
     */
    public function getChapterTree(int $courseId): array
    {
        try {
            $chapters = Chapter::where('course_id', $courseId)
                ->order('parent_id', 'asc')
                ->order('sort', 'asc')
                ->select()
                ->toArray();

            // 转换为树形结构
            $tree = $this->buildTree($chapters);

            return [
                'success' => true,
                'data' => $tree
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '获取章节树失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 构建树形结构
     */
    private function buildTree(array $items, int $parentId = 0): array
    {
        $tree = [];
        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = $this->buildTree($items, $item['id']);
                if ($children) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        return $tree;
    }

    /**
     * 获取章节详情
     */
    public function getChapterDetail(int $chapterId): array
    {
        try {
            $chapter = Chapter::with(['course', 'parent'])->find($chapterId);

            if (!$chapter) {
                return [
                    'success' => false,
                    'message' => '章节不存在'
                ];
            }

            // 追加额外属性
            $chapter->append(['status_text']);

            return [
                'success' => true,
                'data' => $chapter
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '获取章节详情失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 更新章节
     */
    public function updateChapter(int $chapterId, array $data): array
    {
        try {
            $chapter = Chapter::find($chapterId);

            if (!$chapter) {
                return [
                    'success' => false,
                    'message' => '章节不存在'
                ];
            }

            // 如果更新了内容，保存版本历史
            if (isset($data['content']) && $data['content'] !== $chapter->content) {
                $chapter->saveVersion('手动编辑');
            }

            $chapter->save($data);

            // 更新课程统计
            $course = Course::find($chapter->course_id);
            if ($course) {
                $course->updateStats();
            }

            return [
                'success' => true,
                'message' => '章节更新成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '章节更新失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 删除章节
     */
    public function deleteChapter(int $chapterId): array
    {
        try {
            $chapter = Chapter::find($chapterId);

            if (!$chapter) {
                return [
                    'success' => false,
                    'message' => '章节不存在'
                ];
            }

            // 获取所有子孙章节ID
            $descendantIds = $chapter->getDescendantIds();
            $allIds = array_merge([$chapterId], $descendantIds);

            // 删除所有章节
            Chapter::destroy($allIds);

            // 更新课程统计
            $course = Course::find($chapter->course_id);
            if ($course) {
                $course->updateStats();
            }

            return [
                'success' => true,
                'message' => '章节删除成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '章节删除失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 添加章节
     */
    public function addChapter(int $courseId, array $data): array
    {
        try {
            $parentId = $data['parent_id'] ?? 0;
            $level = 1;

            // 如果有父级，计算层级
            if ($parentId > 0) {
                $parent = Chapter::find($parentId);
                if ($parent) {
                    $level = $parent->level + 1;
                }
            }

            // 获取当前最大排序号
            $maxSort = Chapter::where('course_id', $courseId)
                ->where('parent_id', $parentId)
                ->max('sort') ?? -1;

            $chapter = Chapter::create([
                'course_id' => $courseId,
                'parent_id' => $parentId,
                'chapter_number' => $data['chapter_number'],
                'chapter_title' => $data['chapter_title'],
                'chapter_description' => $data['chapter_description'] ?? '',
                'level' => $level,
                'sort' => $maxSort + 1,
                'status' => Chapter::STATUS_NOT_GENERATED,
                'create_time' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'data' => $chapter,
                'message' => '章节添加成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '章节添加失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 调整章节顺序
     */
    public function updateChapterSort(array $sortData): array
    {
        try {
            foreach ($sortData as $item) {
                Chapter::where('id', $item['id'])
                    ->update([
                        'sort' => $item['sort'],
                        'parent_id' => $item['parent_id'] ?? 0,
                    ]);
            }

            return [
                'success' => true,
                'message' => '排序更新成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '排序更新失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取章节的版本历史
     */
    public function getVersionHistory(int $chapterId): array
    {
        try {
            $chapter = Chapter::find($chapterId);

            if (!$chapter) {
                return [
                    'success' => false,
                    'message' => '章节不存在'
                ];
            }

            $versions = \app\index\model\ChapterVersion::where('chapter_id', $chapterId)
                ->order('version_number', 'desc')
                ->limit(20)
                ->select();

            return [
                'success' => true,
                'data' => $versions
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '获取版本历史失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 恢复版本
     */
    public function restoreVersion(int $chapterId, int $versionId): array
    {
        try {
            $chapter = Chapter::find($chapterId);
            $version = \app\index\model\ChapterVersion::find($versionId);

            if (!$chapter || !$version) {
                return [
                    'success' => false,
                    'message' => '章节或版本不存在'
                ];
            }

            if ($version->chapter_id != $chapterId) {
                return [
                    'success' => false,
                    'message' => '版本不匹配'
                ];
            }

            // 保存当前版本
            $chapter->saveVersion('恢复到版本 ' . $version->version_number);

            // 恢复内容
            $chapter->content = $version->content;
            $chapter->save();

            return [
                'success' => true,
                'message' => '版本恢复成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '版本恢复失败: ' . $e->getMessage()
            ];
        }
    }
}
