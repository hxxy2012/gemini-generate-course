<?php
declare(strict_types=1);

namespace app\index\service;

use app\index\model\Course;
use app\index\model\Chapter;
use think\facade\Db;

/**
 * 课程服务类
 */
class CourseService
{
    /**
     * 创建课程
     */
    public function createCourse(array $data, int $userId): array
    {
        try {
            $course = Course::create([
                'user_id' => $userId,
                'course_title' => $data['course_title'],
                'course_description' => $data['course_description'] ?? '',
                'course_topic' => $data['course_topic'],
                'target_audience' => $data['target_audience'] ?? 'beginner',
                'content_style' => $data['content_style'] ?? 'practical',
                'language' => $data['language'] ?? 'zh-CN',
                'chapter_count_suggest' => $data['chapter_count_suggest'] ?? 10,
                'depth_level' => $data['depth_level'] ?? 3,
                'word_count_per_chapter' => $data['word_count_per_chapter'] ?? 1500,
                'include_code' => $data['include_code'] ?? 0,
                'include_exercises' => $data['include_exercises'] ?? 0,
                'special_requirements' => $data['special_requirements'] ?? '',
                'status' => Course::STATUS_DRAFT,
                'create_time' => date('Y-m-d H:i:s'),
            ]);

            return [
                'success' => true,
                'course_id' => $course->id,
                'message' => '课程创建成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '课程创建失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 更新课程
     */
    public function updateCourse(int $courseId, array $data, int $userId): array
    {
        try {
            $course = Course::where('id', $courseId)
                ->where('user_id', $userId)
                ->find();

            if (!$course) {
                return [
                    'success' => false,
                    'message' => '课程不存在或无权限'
                ];
            }

            $course->save($data);

            return [
                'success' => true,
                'message' => '课程更新成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '课程更新失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 删除课程（软删除）
     */
    public function deleteCourse(int $courseId, int $userId): array
    {
        try {
            $course = Course::where('id', $courseId)
                ->where('user_id', $userId)
                ->find();

            if (!$course) {
                return [
                    'success' => false,
                    'message' => '课程不存在或无权限'
                ];
            }

            $course->delete();

            return [
                'success' => true,
                'message' => '课程删除成功'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '课程删除失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取课程列表
     */
    public function getCourseList(int $userId, array $params = []): array
    {
        try {
            $where = [
                ['user_id', '=', $userId]
            ];

            // 状态筛选
            if (isset($params['status']) && $params['status'] !== '') {
                $where[] = ['status', '=', $params['status']];
            }

            // 搜索
            if (!empty($params['keyword'])) {
                $where[] = ['course_title', 'like', '%' . $params['keyword'] . '%'];
            }

            $list = Course::where($where)
                ->order('create_time', 'desc')
                ->paginate([
                    'list_rows' => $params['limit'] ?? 10,
                    'page' => $params['page'] ?? 1,
                ]);

            return [
                'success' => true,
                'data' => $list->items(),
                'total' => $list->total(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '获取课程列表失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取课程详情
     */
    public function getCourseDetail(int $courseId, int $userId): array
    {
        try {
            $course = Course::where('id', $courseId)
                ->where('user_id', $userId)
                ->find();

            if (!$course) {
                return [
                    'success' => false,
                    'message' => '课程不存在或无权限'
                ];
            }

            // 追加额外属性
            $course->append(['status_text', 'progress', 'target_audience_text', 'content_style_text']);

            return [
                'success' => true,
                'data' => $course
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '获取课程详情失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 保存大纲到数据库（从AI生成的结果）
     */
    public function saveOutline(int $courseId, array $outlineData, int $userId): array
    {
        try {
            $course = Course::where('id', $courseId)
                ->where('user_id', $userId)
                ->find();

            if (!$course) {
                return [
                    'success' => false,
                    'message' => '课程不存在或无权限'
                ];
            }

            Db::startTrans();

            try {
                // 更新课程标题和描述（如果AI生成了）
                if (!empty($outlineData['course_title'])) {
                    $course->course_title = $outlineData['course_title'];
                }
                if (!empty($outlineData['course_description'])) {
                    $course->course_description = $outlineData['course_description'];
                }

                // 递归保存章节
                $this->saveChapters($courseId, $outlineData['chapters'] ?? [], 0, 1);

                // 更新课程状态
                $course->status = Course::STATUS_OUTLINE_COMPLETE;
                $course->outline_generated = 1;
                $course->save();

                // 更新统计
                $course->updateStats();

                Db::commit();

                return [
                    'success' => true,
                    'message' => '大纲保存成功'
                ];
            } catch (\Exception $e) {
                Db::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '大纲保存失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 递归保存章节
     */
    private function saveChapters(int $courseId, array $chapters, int $parentId = 0, int $level = 1, string $parentNumber = ''): void
    {
        foreach ($chapters as $index => $chapterData) {
            $chapterNumber = $parentNumber ? $parentNumber . '.' . $chapterData['chapter_number'] : $chapterData['chapter_number'];

            // 创建章节
            $chapter = Chapter::create([
                'course_id' => $courseId,
                'parent_id' => $parentId,
                'chapter_number' => $chapterNumber,
                'chapter_title' => $chapterData['chapter_title'],
                'chapter_description' => $chapterData['chapter_description'] ?? '',
                'level' => $level,
                'sort' => $index,
                'status' => Chapter::STATUS_NOT_GENERATED,
                'create_time' => date('Y-m-d H:i:s'),
            ]);

            // 递归保存子章节（sections）
            if (!empty($chapterData['sections'])) {
                $this->saveChapters($courseId, $chapterData['sections'], $chapter->id, $level + 1, $chapterNumber);
            }

            // 递归保存小节（subsections）
            if (!empty($chapterData['subsections'])) {
                $this->saveChapters($courseId, $chapterData['subsections'], $chapter->id, $level + 1, $chapterNumber);
            }
        }
    }

    /**
     * 获取用户统计数据
     */
    public function getUserStats(int $userId): array
    {
        try {
            $stats = [
                'total_courses' => Course::where('user_id', $userId)->count(),
                'completed_courses' => Course::where('user_id', $userId)
                    ->where('status', Course::STATUS_COMPLETE)
                    ->count(),
                'total_chapters' => Chapter::whereIn('course_id', function($query) use ($userId) {
                    $query->table('cg_course')->where('user_id', $userId)->field('id');
                })->count(),
                'generated_chapters' => Chapter::whereIn('course_id', function($query) use ($userId) {
                    $query->table('cg_course')->where('user_id', $userId)->field('id');
                })->where('status', Chapter::STATUS_GENERATED)->count(),
                'total_words' => Course::where('user_id', $userId)->sum('total_words'),
            ];

            return [
                'success' => true,
                'data' => $stats
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '获取统计数据失败: ' . $e->getMessage()
            ];
        }
    }
}
