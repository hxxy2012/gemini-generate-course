<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\BaseController;
use app\index\service\ChapterService;
use app\index\service\CourseService;

/**
 * 章节控制器
 */
class Chapter extends BaseController
{
    protected $chapterService;
    protected $courseService;

    protected function initialize()
    {
        parent::initialize();
        $this->chapterService = new ChapterService();
        $this->courseService = new CourseService();
    }

    /**
     * 章节详情
     */
    public function detail()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $chapterId = (int)$this->request->param('id');
        $result = $this->chapterService->getChapterDetail($chapterId);

        if (!$result['success']) {
            return $this->error($result['message']);
        }

        // 验证权限
        $chapter = $result['data'];
        if ($chapter['course']['user_id'] != $userId) {
            return $this->error('无权限访问');
        }

        if ($this->request->isAjax()) {
            return $this->success($result['data']);
        }

        return view('chapter/detail', ['chapter' => $result['data']]);
    }

    /**
     * 编辑章节
     */
    public function edit()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $chapterId = (int)$this->request->param('id');

        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = $this->chapterService->updateChapter($chapterId, $data);

            if ($result['success']) {
                return $this->success([], $result['message']);
            }

            return $this->error($result['message']);
        }

        $result = $this->chapterService->getChapterDetail($chapterId);
        if (!$result['success']) {
            return $this->error($result['message']);
        }

        // 验证权限
        $chapter = $result['data'];
        if ($chapter['course']['user_id'] != $userId) {
            return $this->error('无权限访问');
        }

        return view('chapter/edit', ['chapter' => $result['data']]);
    }

    /**
     * 保存章节内容
     */
    public function save()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $chapterId = (int)$this->request->post('chapter_id');
        $content = $this->request->post('content');

        $result = $this->chapterService->updateChapter($chapterId, [
            'content' => $content
        ]);

        if ($result['success']) {
            return $this->success([], $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 添加章节
     */
    public function add()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $courseId = (int)$this->request->post('course_id');
        $data = $this->request->post();

        // 验证权限
        $courseResult = $this->courseService->getCourseDetail($courseId, $userId);
        if (!$courseResult['success']) {
            return $this->error($courseResult['message']);
        }

        $result = $this->chapterService->addChapter($courseId, $data);

        if ($result['success']) {
            return $this->success($result['data'], $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 删除章节
     */
    public function delete()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $chapterId = (int)$this->request->param('id');

        // 验证权限
        $chapterResult = $this->chapterService->getChapterDetail($chapterId);
        if (!$chapterResult['success']) {
            return $this->error($chapterResult['message']);
        }

        $chapter = $chapterResult['data'];
        if ($chapter['course']['user_id'] != $userId) {
            return $this->error('无权限操作');
        }

        $result = $this->chapterService->deleteChapter($chapterId);

        if ($result['success']) {
            return $this->success([], $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 更新章节排序
     */
    public function updateSort()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $sortData = $this->request->post('sort_data');

        if (empty($sortData) || !is_array($sortData)) {
            return $this->error('参数错误');
        }

        $result = $this->chapterService->updateChapterSort($sortData);

        if ($result['success']) {
            return $this->success([], $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 获取版本历史
     */
    public function versionHistory()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $chapterId = (int)$this->request->param('chapter_id');

        // 验证权限
        $chapterResult = $this->chapterService->getChapterDetail($chapterId);
        if (!$chapterResult['success']) {
            return $this->error($chapterResult['message']);
        }

        $chapter = $chapterResult['data'];
        if ($chapter['course']['user_id'] != $userId) {
            return $this->error('无权限访问');
        }

        $result = $this->chapterService->getVersionHistory($chapterId);

        if ($result['success']) {
            return $this->success($result['data']);
        }

        return $this->error($result['message']);
    }

    /**
     * 恢复版本
     */
    public function restoreVersion()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $chapterId = (int)$this->request->post('chapter_id');
        $versionId = (int)$this->request->post('version_id');

        // 验证权限
        $chapterResult = $this->chapterService->getChapterDetail($chapterId);
        if (!$chapterResult['success']) {
            return $this->error($chapterResult['message']);
        }

        $chapter = $chapterResult['data'];
        if ($chapter['course']['user_id'] != $userId) {
            return $this->error('无权限操作');
        }

        $result = $this->chapterService->restoreVersion($chapterId, $versionId);

        if ($result['success']) {
            return $this->success([], $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 获取章节状态（用于轮询）
     */
    public function getStatus()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $chapterId = (int)$this->request->param('chapter_id');

        $result = $this->chapterService->getChapterDetail($chapterId);

        if ($result['success']) {
            return $this->success([
                'status' => $result['data']['status'],
                'status_text' => $result['data']['status_text'],
                'error_message' => $result['data']['error_message'] ?? '',
            ]);
        }

        return $this->error($result['message']);
    }
}
