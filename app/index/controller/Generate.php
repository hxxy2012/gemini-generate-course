<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\BaseController;
use app\index\service\GenerateService;
use app\index\service\CourseService;
use app\index\service\GeminiService;

/**
 * AI生成控制器
 */
class Generate extends BaseController
{
    protected $generateService;
    protected $courseService;

    protected function initialize()
    {
        parent::initialize();
        $this->generateService = new GenerateService();
        $this->courseService = new CourseService();
    }

    /**
     * 生成课程大纲
     */
    public function generateOutline()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $courseId = (int)$this->request->post('course_id');

        // 验证课程所属
        $courseResult = $this->courseService->getCourseDetail($courseId, $userId);
        if (!$courseResult['success']) {
            return $this->error($courseResult['message']);
        }

        // 创建生成任务
        $result = $this->generateService->createOutlineTask($courseId, $userId);

        if ($result['success']) {
            return $this->success($result, $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 生成章节内容（单个）
     */
    public function generateChapter()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $chapterId = (int)$this->request->post('chapter_id');

        // 创建生成任务
        $result = $this->generateService->createContentTask($chapterId, $userId);

        if ($result['success']) {
            return $this->success($result, $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 批量生成章节内容
     */
    public function batchGenerate()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $courseId = (int)$this->request->post('course_id');

        // 验证课程所属
        $courseResult = $this->courseService->getCourseDetail($courseId, $userId);
        if (!$courseResult['success']) {
            return $this->error($courseResult['message']);
        }

        // 批量创建生成任务
        $result = $this->generateService->createBatchContentTasks($courseId, $userId);

        if ($result['success']) {
            return $this->success($result, $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 获取任务状态
     */
    public function taskStatus()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $taskId = (int)$this->request->param('task_id');

        $result = $this->generateService->getTaskStatus($taskId);

        if ($result['success']) {
            return $this->success($result['data']);
        }

        return $this->error($result['message']);
    }

    /**
     * AI文本优化
     */
    public function optimizeText()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $text = $this->request->post('text');
        $action = $this->request->post('action', 'rewrite'); // rewrite/expand/shorten/translate

        if (empty($text)) {
            return $this->error('文本不能为空');
        }

        try {
            $geminiService = new GeminiService($userId);

            // 检查配额
            if (!$geminiService->checkUserQuota($userId)) {
                return $this->error('API配额不足，请明天再试或升级套餐');
            }

            $result = $geminiService->optimizeText($text, $action);

            if ($result['success']) {
                return $this->success($result, '优化成功');
            }

            return $this->error($result['error']);
        } catch (\Exception $e) {
            return $this->error('优化失败: ' . $e->getMessage());
        }
    }

    /**
     * 测试Gemini API连接
     */
    public function testApi()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        try {
            $geminiService = new GeminiService($userId);

            $result = $geminiService->generateChapterContent([
                'chapter_number' => '1',
                'chapter_title' => '测试章节',
                'chapter_description' => '这是一个API连接测试',
            ], [
                'course_title' => 'API测试',
                'word_count_per_chapter' => 100,
            ]);

            if ($result['success']) {
                return $this->success([
                    'message' => 'API连接成功',
                    'token_used' => $result['token_used'],
                    'preview' => mb_substr($result['content'], 0, 200) . '...',
                ], 'API连接成功');
            }

            return $this->error('API连接失败: ' . $result['error']);
        } catch (\Exception $e) {
            return $this->error('API测试失败: ' . $e->getMessage());
        }
    }
}
