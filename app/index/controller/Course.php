<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\BaseController;
use app\index\service\CourseService;
use app\index\service\ChapterService;

/**
 * 课程控制器
 */
class Course extends BaseController
{
    protected $courseService;
    protected $chapterService;

    protected function initialize()
    {
        parent::initialize();
        $this->courseService = new CourseService();
        $this->chapterService = new ChapterService();
    }

    /**
     * 课程列表页面
     */
    public function index()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return redirect('/login');
        }

        if ($this->request->isAjax()) {
            $params = $this->request->param();
            $result = $this->courseService->getCourseList($userId, $params);

            if ($result['success']) {
                return json([
                    'code' => 0,
                    'msg' => '',
                    'count' => $result['total'],
                    'data' => $result['data'],
                ]);
            }

            return $this->error($result['message']);
        }

        return view('course/index');
    }

    /**
     * 创建课程页面
     */
    public function create()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return redirect('/login');
        }

        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = $this->courseService->createCourse($data, $userId);

            if ($result['success']) {
                return $this->success($result, $result['message']);
            }

            return $this->error($result['message']);
        }

        return view('course/create');
    }

    /**
     * 课程详情
     */
    public function detail()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return redirect('/login');
        }

        $courseId = (int)$this->request->param('id');
        $result = $this->courseService->getCourseDetail($courseId, $userId);

        if (!$result['success']) {
            if ($this->request->isAjax()) {
                return $this->error($result['message']);
            }
            return redirect('/course/index')->with('error', $result['message']);
        }

        if ($this->request->isAjax()) {
            return $this->success($result['data']);
        }

        return view('course/detail', ['course' => $result['data']]);
    }

    /**
     * 编辑课程
     */
    public function edit()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return redirect('/login');
        }

        $courseId = (int)$this->request->param('id');

        if ($this->request->isPost()) {
            $data = $this->request->post();
            $result = $this->courseService->updateCourse($courseId, $data, $userId);

            if ($result['success']) {
                return $this->success([], $result['message']);
            }

            return $this->error($result['message']);
        }

        $result = $this->courseService->getCourseDetail($courseId, $userId);
        if (!$result['success']) {
            return redirect('/course/index')->with('error', $result['message']);
        }

        return view('course/edit', ['course' => $result['data']]);
    }

    /**
     * 删除课程
     */
    public function delete()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $courseId = (int)$this->request->param('id');
        $result = $this->courseService->deleteCourse($courseId, $userId);

        if ($result['success']) {
            return $this->success([], $result['message']);
        }

        return $this->error($result['message']);
    }

    /**
     * 获取章节树
     */
    public function getChapterTree()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $courseId = (int)$this->request->param('course_id');

        // 验证课程所属
        $courseResult = $this->courseService->getCourseDetail($courseId, $userId);
        if (!$courseResult['success']) {
            return $this->error($courseResult['message']);
        }

        $result = $this->chapterService->getChapterTree($courseId);

        if ($result['success']) {
            return $this->success($result['data']);
        }

        return $this->error($result['message']);
    }

    /**
     * 用户统计数据
     */
    public function stats()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $result = $this->courseService->getUserStats($userId);

        if ($result['success']) {
            return $this->success($result['data']);
        }

        return $this->error($result['message']);
    }
}
