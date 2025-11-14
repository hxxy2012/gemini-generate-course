<?php
declare(strict_types=1);

namespace app\index\service;

use app\index\model\GenerateTask;
use app\index\model\Course;
use app\index\model\Chapter;
use think\facade\Log;

/**
 * AI生成服务类
 */
class GenerateService
{
    private GeminiService $geminiService;
    private CourseService $courseService;

    public function __construct()
    {
        $this->courseService = new CourseService();
    }

    /**
     * 创建大纲生成任务
     */
    public function createOutlineTask(int $courseId, int $userId): array
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

            // 检查用户配额
            $this->geminiService = new GeminiService($userId);
            if (!$this->geminiService->checkUserQuota($userId)) {
                return [
                    'success' => false,
                    'message' => 'API配额不足，请明天再试或升级套餐'
                ];
            }

            // 构建提示词（先构建，以便保存）
            $courseData = [
                'course_topic' => $course->course_topic,
                'target_audience' => $course->target_audience,
                'chapter_count_suggest' => $course->chapter_count_suggest,
                'depth_level' => $course->depth_level,
                'special_requirements' => $course->special_requirements,
            ];

            // 创建任务
            $task = GenerateTask::create([
                'task_type' => GenerateTask::TYPE_OUTLINE,
                'course_id' => $courseId,
                'user_id' => $userId,
                'prompt' => '大纲生成任务',
                'params' => json_encode($courseData),
                'status' => GenerateTask::STATUS_PENDING,
                'priority' => 8, // 大纲生成优先级较高
                'create_time' => date('Y-m-d H:i:s'),
            ]);

            // 更新课程状态
            $course->status = Course::STATUS_OUTLINE_GENERATING;
            $course->save();

            return [
                'success' => true,
                'task_id' => $task->id,
                'message' => '大纲生成任务已创建，请稍后查看'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '创建任务失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 处理大纲生成任务
     */
    public function processOutlineTask(GenerateTask $task): array
    {
        try {
            $this->geminiService = new GeminiService($task->user_id);

            $course = Course::find($task->course_id);
            if (!$course) {
                throw new \Exception('课程不存在');
            }

            $courseData = json_decode($task->params, true);

            // 调用Gemini生成大纲
            $result = $this->geminiService->generateOutline($courseData);

            if ($result['success']) {
                // 保存大纲到数据库
                $saveResult = $this->courseService->saveOutline(
                    $task->course_id,
                    $result['outline'],
                    $task->user_id
                );

                if ($saveResult['success']) {
                    return [
                        'success' => true,
                        'content' => json_encode($result['outline']),
                        'token_used' => $result['token_used']
                    ];
                } else {
                    throw new \Exception('保存大纲失败: ' . $saveResult['message']);
                }
            } else {
                throw new \Exception($result['error']);
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 创建章节内容生成任务
     */
    public function createContentTask(int $chapterId, int $userId): array
    {
        try {
            $chapter = Chapter::with('course')->find($chapterId);

            if (!$chapter) {
                return [
                    'success' => false,
                    'message' => '章节不存在'
                ];
            }

            if ($chapter->course->user_id != $userId) {
                return [
                    'success' => false,
                    'message' => '无权限操作'
                ];
            }

            // 检查用户配额
            $this->geminiService = new GeminiService($userId);
            if (!$this->geminiService->checkUserQuota($userId)) {
                return [
                    'success' => false,
                    'message' => 'API配额不足，请明天再试或升级套餐'
                ];
            }

            // 构建上下文
            $context = [
                'course_title' => $chapter->course->course_title,
                'course_description' => $chapter->course->course_description,
                'target_audience' => $chapter->course->target_audience,
                'content_style' => $chapter->course->content_style,
                'word_count_per_chapter' => $chapter->course->word_count_per_chapter,
                'include_code' => $chapter->course->include_code,
                'include_exercises' => $chapter->course->include_exercises,
            ];

            // 如果有父章节，添加父章节信息
            if ($chapter->parent_id > 0) {
                $parent = Chapter::find($chapter->parent_id);
                if ($parent) {
                    $context['parent_title'] = $parent->chapter_title;
                }
            }

            $chapterData = [
                'chapter_number' => $chapter->chapter_number,
                'chapter_title' => $chapter->chapter_title,
                'chapter_description' => $chapter->chapter_description,
            ];

            $params = [
                'chapter_data' => $chapterData,
                'context' => $context,
            ];

            // 创建任务
            $task = GenerateTask::create([
                'task_type' => GenerateTask::TYPE_CONTENT,
                'course_id' => $chapter->course_id,
                'chapter_id' => $chapterId,
                'user_id' => $userId,
                'prompt' => '章节内容生成',
                'params' => json_encode($params),
                'status' => GenerateTask::STATUS_PENDING,
                'priority' => 5,
                'create_time' => date('Y-m-d H:i:s'),
            ]);

            // 更新章节状态
            $chapter->status = Chapter::STATUS_GENERATING;
            $chapter->save();

            // 更新课程状态
            $course = Course::find($chapter->course_id);
            if ($course && $course->status < Course::STATUS_CONTENT_GENERATING) {
                $course->status = Course::STATUS_CONTENT_GENERATING;
                $course->save();
            }

            return [
                'success' => true,
                'task_id' => $task->id,
                'message' => '内容生成任务已创建，请稍后查看'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '创建任务失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 处理章节内容生成任务
     */
    public function processContentTask(GenerateTask $task): array
    {
        try {
            $this->geminiService = new GeminiService($task->user_id);

            $chapter = Chapter::with('course')->find($task->chapter_id);
            if (!$chapter) {
                throw new \Exception('章节不存在');
            }

            $params = json_decode($task->params, true);
            $chapterData = $params['chapter_data'];
            $context = $params['context'];

            // 调用Gemini生成内容
            $result = $this->geminiService->generateChapterContent($chapterData, $context);

            if ($result['success']) {
                // 保存内容
                $chapter->content = $result['content'];
                $chapter->status = Chapter::STATUS_GENERATED;
                $chapter->ai_model = config('gemini.model');
                $chapter->token_used = $result['token_used'];
                $chapter->generate_time = date('Y-m-d H:i:s');
                $chapter->save();

                // 更新课程统计
                $course = Course::find($chapter->course_id);
                if ($course) {
                    $course->updateStats();

                    // 检查是否所有章节都已生成
                    if ($course->generated_chapters >= $course->total_chapters) {
                        $course->status = Course::STATUS_COMPLETE;
                        $course->save();
                    }
                }

                return [
                    'success' => true,
                    'content' => $result['content'],
                    'token_used' => $result['token_used']
                ];
            } else {
                // 更新章节状态为失败
                $chapter->status = Chapter::STATUS_FAILED;
                $chapter->error_message = $result['error'];
                $chapter->save();

                throw new \Exception($result['error']);
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 批量创建内容生成任务
     */
    public function createBatchContentTasks(int $courseId, int $userId): array
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

            // 获取所有未生成的叶子节点章节
            $chapters = Chapter::where('course_id', $courseId)
                ->where('status', Chapter::STATUS_NOT_GENERATED)
                ->select();

            $leafChapters = [];
            foreach ($chapters as $chapter) {
                if ($chapter->isLeaf()) {
                    $leafChapters[] = $chapter;
                }
            }

            if (empty($leafChapters)) {
                return [
                    'success' => false,
                    'message' => '没有需要生成的章节'
                ];
            }

            // 检查用户配额
            $this->geminiService = new GeminiService($userId);
            if (!$this->geminiService->checkUserQuota($userId)) {
                return [
                    'success' => false,
                    'message' => 'API配额不足，请明天再试或升级套餐'
                ];
            }

            $taskIds = [];
            foreach ($leafChapters as $chapter) {
                $result = $this->createContentTask($chapter->id, $userId);
                if ($result['success']) {
                    $taskIds[] = $result['task_id'];
                }
            }

            return [
                'success' => true,
                'count' => count($taskIds),
                'task_ids' => $taskIds,
                'message' => '已创建 ' . count($taskIds) . ' 个生成任务'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '批量创建任务失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取任务状态
     */
    public function getTaskStatus(int $taskId): array
    {
        try {
            $task = GenerateTask::find($taskId);

            if (!$task) {
                return [
                    'success' => false,
                    'message' => '任务不存在'
                ];
            }

            return [
                'success' => true,
                'data' => [
                    'status' => $task->status,
                    'status_text' => $task->status_text,
                    'error_message' => $task->error_message,
                ]
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '获取任务状态失败: ' . $e->getMessage()
            ];
        }
    }
}
