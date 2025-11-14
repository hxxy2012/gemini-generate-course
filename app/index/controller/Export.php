<?php
declare(strict_types=1);

namespace app\index\controller;

use app\common\BaseController;
use app\index\service\ExportService;
use app\index\service\CourseService;

/**
 * 导出控制器
 */
class Export extends BaseController
{
    protected $exportService;
    protected $courseService;

    protected function initialize()
    {
        parent::initialize();
        $this->exportService = new ExportService();
        $this->courseService = new CourseService();
    }

    /**
     * 导出课程
     */
    public function export()
    {
        $userId = $this->getUserId();
        if (!$userId) {
            return $this->error('未登录', 401);
        }

        $courseId = (int)$this->request->post('course_id');
        $format = $this->request->post('format', 'md'); // docx, pdf, md, html

        // 验证课程所属
        $courseResult = $this->courseService->getCourseDetail($courseId, $userId);
        if (!$courseResult['success']) {
            return $this->error($courseResult['message']);
        }

        // 根据格式导出
        switch ($format) {
            case 'docx':
                $result = $this->exportService->exportToWord($courseId, $userId);
                break;
            case 'md':
                $result = $this->exportService->exportToMarkdown($courseId, $userId);
                break;
            case 'html':
                $result = $this->exportService->exportToHtml($courseId, $userId);
                break;
            case 'pdf':
                return $this->error('PDF导出功能开发中');
            default:
                return $this->error('不支持的导出格式');
        }

        if ($result['success']) {
            return $this->success($result, '导出成功');
        }

        return $this->error($result['message']);
    }

    /**
     * 下载导出文件
     */
    public function download()
    {
        $filename = $this->request->param('filename');

        if (!$filename || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $filename)) {
            return $this->error('无效的文件名');
        }

        $filepath = public_path() . 'uploads/exports/' . $filename;

        if (!file_exists($filepath)) {
            return $this->error('文件不存在');
        }

        return download($filepath, $filename);
    }
}
