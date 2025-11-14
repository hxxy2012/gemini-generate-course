<?php
declare(strict_types=1);

namespace app\index\service;

use app\index\model\Course;
use app\index\model\Chapter;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Font;
use TCPDF;

/**
 * 导出服务类
 */
class ExportService
{
    /**
     * 导出为Word
     */
    public function exportToWord(int $courseId, int $userId): array
    {
        try {
            $course = Course::where('id', $courseId)
                ->where('user_id', $userId)
                ->find();

            if (!$course) {
                return ['success' => false, 'message' => '课程不存在'];
            }

            $phpWord = new PhpWord();
            $phpWord->setDefaultFontName('Microsoft YaHei');
            $phpWord->setDefaultFontSize(12);

            // 添加封面
            $section = $phpWord->addSection();
            $section->addText(
                $course->course_title,
                ['size' => 28, 'bold' => true],
                ['alignment' => 'center', 'spaceAfter' => 400]
            );

            if ($course->course_description) {
                $section->addText(
                    $course->course_description,
                    ['size' => 14],
                    ['alignment' => 'center', 'spaceAfter' => 200]
                );
            }

            $section->addText(
                '生成时间：' . date('Y-m-d H:i:s'),
                ['size' => 10, 'color' => '999999'],
                ['alignment' => 'center']
            );

            $section->addPageBreak();

            // 添加目录
            $section->addTitle('目录', 1);
            $chapters = Chapter::where('course_id', $courseId)
                ->where('status', Chapter::STATUS_GENERATED)
                ->order('sort', 'asc')
                ->select();

            foreach ($chapters as $chapter) {
                $indent = ($chapter->level - 1) * 300;
                $section->addText(
                    $chapter->chapter_number . ' ' . $chapter->chapter_title,
                    ['size' => 12],
                    ['indentation' => ['left' => $indent]]
                );
            }

            $section->addPageBreak();

            // 添加内容
            $this->addChaptersToWord($phpWord, $chapters);

            // 保存文件
            $filename = $this->sanitizeFilename($course->course_title) . '_' . date('YmdHis') . '.docx';
            $filepath = public_path() . 'uploads/exports/' . $filename;

            if (!is_dir(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
            $objWriter->save($filepath);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => '/uploads/exports/' . $filename
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 添加章节内容到Word
     */
    private function addChaptersToWord(PhpWord $phpWord, $chapters): void
    {
        foreach ($chapters as $chapter) {
            if (!$chapter->content) {
                continue;
            }

            $section = $phpWord->addSection();

            // 添加标题
            $section->addTitle(
                $chapter->chapter_number . ' ' . $chapter->chapter_title,
                $chapter->level
            );

            // 解析Markdown内容
            $lines = explode("\n", $chapter->content);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                // 简单的Markdown解析
                if (preg_match('/^#{1,6}\s+(.+)$/', $line, $matches)) {
                    // 标题
                    $level = strlen(substr($line, 0, strpos($line, ' ')));
                    $section->addTitle($matches[1], $level + 1);
                } elseif (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                    // 列表
                    $section->addListItem($matches[1]);
                } elseif (preg_match('/^```/', $line)) {
                    // 代码块（跳过）
                    continue;
                } else {
                    // 普通文本
                    $section->addText($line);
                }
            }
        }
    }

    /**
     * 导出为Markdown
     */
    public function exportToMarkdown(int $courseId, int $userId): array
    {
        try {
            $course = Course::where('id', $courseId)
                ->where('user_id', $userId)
                ->find();

            if (!$course) {
                return ['success' => false, 'message' => '课程不存在'];
            }

            $chapters = Chapter::where('course_id', $courseId)
                ->where('status', Chapter::STATUS_GENERATED)
                ->order('parent_id', 'asc')
                ->order('sort', 'asc')
                ->select();

            $markdown = "# {$course->course_title}\n\n";

            if ($course->course_description) {
                $markdown .= "> {$course->course_description}\n\n";
            }

            $markdown .= "---\n\n";
            $markdown .= "**生成时间**: " . date('Y-m-d H:i:s') . "\n\n";
            $markdown .= "**章节数**: {$course->total_chapters}\n\n";
            $markdown .= "**总字数**: {$course->total_words}\n\n";
            $markdown .= "---\n\n";

            // 添加目录
            $markdown .= "## 目录\n\n";
            foreach ($chapters as $chapter) {
                $indent = str_repeat('  ', $chapter->level - 1);
                $markdown .= "{$indent}- [{$chapter->chapter_number} {$chapter->chapter_title}](#" .
                    $this->generateAnchor($chapter->chapter_number . '-' . $chapter->chapter_title) . ")\n";
            }
            $markdown .= "\n---\n\n";

            // 添加内容
            foreach ($chapters as $chapter) {
                if (!$chapter->content) {
                    continue;
                }

                $markdown .= $chapter->content . "\n\n";
                $markdown .= "---\n\n";
            }

            // 保存文件
            $filename = $this->sanitizeFilename($course->course_title) . '_' . date('YmdHis') . '.md';
            $filepath = public_path() . 'uploads/exports/' . $filename;

            if (!is_dir(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }

            file_put_contents($filepath, $markdown);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => '/uploads/exports/' . $filename
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 导出为HTML
     */
    public function exportToHtml(int $courseId, int $userId): array
    {
        try {
            $course = Course::where('id', $courseId)
                ->where('user_id', $userId)
                ->find();

            if (!$course) {
                return ['success' => false, 'message' => '课程不存在'];
            }

            $chapters = Chapter::where('course_id', $courseId)
                ->where('status', Chapter::STATUS_GENERATED)
                ->order('parent_id', 'asc')
                ->order('sort', 'asc')
                ->select();

            $html = $this->generateHtmlTemplate($course, $chapters);

            // 保存文件
            $filename = $this->sanitizeFilename($course->course_title) . '_' . date('YmdHis') . '.html';
            $filepath = public_path() . 'uploads/exports/' . $filename;

            if (!is_dir(dirname($filepath))) {
                mkdir(dirname($filepath), 0755, true);
            }

            file_put_contents($filepath, $html);

            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'url' => '/uploads/exports/' . $filename
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 生成HTML模板
     */
    private function generateHtmlTemplate(Course $course, $chapters): string
    {
        $parsedown = new \Parsedown();

        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$course->course_title}</title>
    <link rel="stylesheet" href="https://cdn.staticfile.org/github-markdown-css/5.1.0/github-markdown.min.css">
    <style>
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            line-height: 1.6;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            padding-bottom: 30px;
            border-bottom: 2px solid #009688;
            margin-bottom: 40px;
        }
        .header h1 {
            font-size: 36px;
            margin: 0 0 15px 0;
            color: #333;
        }
        .header p {
            color: #666;
            font-size: 16px;
        }
        .toc {
            background: #f8f8f8;
            padding: 20px;
            border-radius: 4px;
            margin-bottom: 40px;
        }
        .toc h2 {
            margin-top: 0;
            color: #009688;
        }
        .toc ul {
            list-style: none;
            padding-left: 0;
        }
        .toc li {
            padding: 5px 0;
        }
        .toc a {
            color: #333;
            text-decoration: none;
        }
        .toc a:hover {
            color: #009688;
        }
        .chapter {
            margin-bottom: 50px;
            padding-top: 20px;
        }
        .chapter h2 {
            color: #009688;
            border-bottom: 2px solid #009688;
            padding-bottom: 10px;
        }
        .footer {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid #eee;
            color: #999;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{$course->course_title}</h1>
            <p>{$course->course_description}</p>
            <p style="font-size: 14px; color: #999;">生成时间: {$course->create_time} | 总字数: {$course->total_words}</p>
        </div>

        <div class="toc">
            <h2>目录</h2>
            <ul>
HTML;

        foreach ($chapters as $chapter) {
            $indent = str_repeat('&nbsp;&nbsp;', ($chapter->level - 1) * 2);
            $html .= "<li>{$indent}<a href=\"#chapter-{$chapter->id}\">{$chapter->chapter_number} {$chapter->chapter_title}</a></li>\n";
        }

        $html .= <<<HTML
            </ul>
        </div>

        <div class="content markdown-body">
HTML;

        foreach ($chapters as $chapter) {
            if (!$chapter->content) {
                continue;
            }

            $html .= "<div class=\"chapter\" id=\"chapter-{$chapter->id}\">\n";
            $html .= $parsedown->text($chapter->content);
            $html .= "</div>\n";
        }

        $html .= <<<HTML
        </div>

        <div class="footer">
            <p>© 2024 CourseGenius - AI驱动的课程生成系统</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * 清理文件名
     */
    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9\x{4e00}-\x{9fa5}_-]/u', '_', $filename);
        return substr($filename, 0, 100);
    }

    /**
     * 生成锚点
     */
    private function generateAnchor(string $text): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', $text));
    }
}
