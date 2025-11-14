<?php
declare(strict_types=1);

namespace app\index\service;

use app\index\model\ApiLog;
use app\index\model\User;
use think\facade\Log;

/**
 * Gemini API 服务类
 */
class GeminiService
{
    private string $apiKey;
    private string $apiEndpoint;
    private string $model;
    private int $timeout;
    private ?int $userId = null;

    public function __construct(?int $userId = null)
    {
        $this->apiKey = config('gemini.api_key') ?: env('gemini.api_key', '');
        $this->apiEndpoint = config('gemini.api_endpoint');
        $this->model = config('gemini.model');
        $this->timeout = config('gemini.timeout');
        $this->userId = $userId;

        if (empty($this->apiKey)) {
            throw new \Exception('Gemini API Key 未配置');
        }
    }

    /**
     * 生成课程大纲
     * @param array $courseData 课程数据
     * @return array
     */
    public function generateOutline(array $courseData): array
    {
        $prompt = $this->buildOutlinePrompt($courseData);
        $params = config('gemini.outline_generation');

        $response = $this->callGeminiAPI($prompt, $params);

        if ($response['success']) {
            try {
                // 解析JSON格式的大纲
                $outline = $this->parseOutlineJSON($response['content']);
                return [
                    'success' => true,
                    'outline' => $outline,
                    'token_used' => $response['token_used']
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => 'JSON解析失败: ' . $e->getMessage()
                ];
            }
        }

        return [
            'success' => false,
            'error' => $response['error']
        ];
    }

    /**
     * 生成章节内容
     * @param array $chapterData 章节数据
     * @param array $context 上下文信息
     * @return array
     */
    public function generateChapterContent(array $chapterData, array $context = []): array
    {
        $prompt = $this->buildContentPrompt($chapterData, $context);
        $params = config('gemini.content_generation');

        $response = $this->callGeminiAPI($prompt, $params);

        if ($response['success']) {
            return [
                'success' => true,
                'content' => $response['content'],
                'token_used' => $response['token_used']
            ];
        }

        return [
            'success' => false,
            'error' => $response['error']
        ];
    }

    /**
     * AI文本优化
     * @param string $text 原文本
     * @param string $action 操作类型：rewrite/expand/shorten/translate
     * @return array
     */
    public function optimizeText(string $text, string $action): array
    {
        $prompts = [
            'rewrite' => "请用不同的表达方式重写以下内容，保持原意不变：\n\n{$text}",
            'expand' => "请扩展以下内容，增加更多细节和例子：\n\n{$text}",
            'shorten' => "请精简以下内容，保留核心观点：\n\n{$text}",
            'translate' => "请将以下内容翻译成英文：\n\n{$text}",
        ];

        $prompt = $prompts[$action] ?? $prompts['rewrite'];

        $response = $this->callGeminiAPI($prompt, [
            'temperature' => 0.7,
            'maxOutputTokens' => 2048,
        ]);

        if ($response['success']) {
            return [
                'success' => true,
                'result' => $response['content'],
                'token_used' => $response['token_used']
            ];
        }

        return [
            'success' => false,
            'error' => $response['error']
        ];
    }

    /**
     * 调用Gemini API
     * @param string $prompt
     * @param array $params
     * @return array
     */
    private function callGeminiAPI(string $prompt, array $params = []): array
    {
        $startTime = microtime(true);
        $url = $this->apiEndpoint . $this->model . ':generateContent?key=' . $this->apiKey;

        $requestData = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => $params
        ];

        try {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $duration = (microtime(true) - $startTime) * 1000; // 毫秒

            if ($error) {
                $this->logAPICall(false, $requestData, null, $error, $duration);
                return [
                    'success' => false,
                    'error' => 'API请求失败: ' . $error
                ];
            }

            $result = json_decode($response, true);

            if ($httpCode !== 200) {
                $errorMsg = $result['error']['message'] ?? '未知错误';
                $this->logAPICall(false, $requestData, $result, $errorMsg, $duration, $httpCode);
                return [
                    'success' => false,
                    'error' => 'API返回错误 (' . $httpCode . '): ' . $errorMsg
                ];
            }

            // 提取生成的文本
            $content = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
            $tokenUsed = $result['usageMetadata']['totalTokenCount'] ?? 0;

            $this->logAPICall(true, $requestData, $result, null, $duration, $httpCode, $tokenUsed);

            // 更新用户配额
            if ($this->userId) {
                $this->updateUserQuota($this->userId);
            }

            return [
                'success' => true,
                'content' => $content,
                'token_used' => $tokenUsed,
                'raw_response' => $result
            ];

        } catch (\Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            $this->logAPICall(false, $requestData, null, $e->getMessage(), $duration);

            return [
                'success' => false,
                'error' => '系统异常: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 构建大纲生成Prompt
     */
    private function buildOutlinePrompt(array $courseData): string
    {
        $prompt = "你是一位资深的课程设计专家。请根据以下要求生成一个完整的课程大纲。\n\n";
        $prompt .= "课程主题：{$courseData['course_topic']}\n";
        $prompt .= "目标受众：{$courseData['target_audience']}\n";
        $prompt .= "建议章节数：{$courseData['chapter_count_suggest']}\n";
        $prompt .= "深度级别：{$courseData['depth_level']}级目录\n";

        if (!empty($courseData['special_requirements'])) {
            $prompt .= "特殊要求：{$courseData['special_requirements']}\n";
        }

        $prompt .= "\n请按照以下JSON格式输出课程大纲（确保输出纯JSON，不要包含任何markdown标记或其他文字）：\n";
        $prompt .= <<<'JSON'
{
  "course_title": "课程标题",
  "course_description": "课程简介",
  "chapters": [
    {
      "chapter_number": "1",
      "chapter_title": "第一章标题",
      "chapter_description": "章节简介",
      "sections": [
        {
          "section_number": "1.1",
          "section_title": "节标题",
          "section_description": "节简介",
          "subsections": [
            {
              "subsection_number": "1.1.1",
              "subsection_title": "小节标题",
              "subsection_description": "小节简介"
            }
          ]
        }
      ]
    }
  ]
}
JSON;

        $prompt .= "\n\n要求：\n";
        $prompt .= "1. 大纲结构清晰，循序渐进\n";
        $prompt .= "2. 每个章节有明确的学习目标\n";
        $prompt .= "3. 标题简洁明了，描述准确\n";
        $prompt .= "4. 内容覆盖主题的核心知识点\n";
        $prompt .= "5. 确保输出严格符合JSON格式，不要有任何额外的文字、markdown标记或代码块标记\n";
        $prompt .= "6. 根据深度级别{$courseData['depth_level']}，生成对应层级的目录结构\n";

        return $prompt;
    }

    /**
     * 构建内容生成Prompt
     */
    private function buildContentPrompt(array $chapterData, array $context): string
    {
        $prompt = "你是一位资深的课程内容创作专家。请根据以下信息，为课程章节创作详细的教学内容。\n\n";

        // 课程信息
        if (!empty($context['course_title'])) {
            $prompt .= "课程信息：\n";
            $prompt .= "- 课程标题：{$context['course_title']}\n";
            if (!empty($context['course_description'])) {
                $prompt .= "- 课程描述：{$context['course_description']}\n";
            }
            if (!empty($context['target_audience'])) {
                $prompt .= "- 目标受众：{$context['target_audience']}\n";
            }
        }

        // 当前章节信息
        $prompt .= "\n当前章节信息：\n";
        $prompt .= "- 章节编号：{$chapterData['chapter_number']}\n";
        $prompt .= "- 章节标题：{$chapterData['chapter_title']}\n";
        if (!empty($chapterData['chapter_description'])) {
            $prompt .= "- 章节描述：{$chapterData['chapter_description']}\n";
        }

        // 上下文信息
        if (!empty($context['parent_title'])) {
            $prompt .= "- 上级章节：{$context['parent_title']}\n";
        }

        // 内容要求
        $prompt .= "\n内容要求：\n";
        $wordCount = $context['word_count_per_chapter'] ?? 1500;
        $prompt .= "- 字数要求：{$wordCount}字左右\n";

        if (!empty($context['content_style'])) {
            $styleMap = [
                'formal' => '正式学术',
                'casual' => '通俗易懂',
                'humorous' => '幽默风趣',
                'practical' => '实战导向',
            ];
            $styleText = $styleMap[$context['content_style']] ?? $context['content_style'];
            $prompt .= "- 内容风格：{$styleText}\n";
        }

        if (!empty($context['include_code'])) {
            $prompt .= "- 包含代码示例：是\n";
        }

        if (!empty($context['include_exercises'])) {
            $prompt .= "- 包含练习题：是\n";
        }

        $prompt .= "\n请按照以下Markdown格式输出（不要包含```markdown标记）：\n\n";
        $prompt .= "# {$chapterData['chapter_title']}\n\n";
        $prompt .= "## 学习目标\n";
        $prompt .= "- 列出3-5个具体的学习目标\n\n";
        $prompt .= "## 知识点讲解\n";
        $prompt .= "（详细的知识点讲解，使用清晰的结构）\n\n";

        if (!empty($context['include_code'])) {
            $prompt .= "## 代码示例\n";
            $prompt .= "（如果需要，提供完整的代码示例，带注释）\n\n";
        }

        if (!empty($context['include_exercises'])) {
            $prompt .= "## 实战练习\n";
            $prompt .= "（如果需要，提供2-3个练习题，包含题目描述和思路提示）\n\n";
        }

        $prompt .= "## 本章小结\n";
        $prompt .= "（总结本章核心内容）\n\n";
        $prompt .= "## 延伸阅读\n";
        $prompt .= "（可选：推荐相关资料）\n\n";

        $prompt .= "要求：\n";
        $prompt .= "1. 内容准确、专业、易懂\n";
        $prompt .= "2. 结构清晰，逻辑严密\n";
        $prompt .= "3. 理论与实践结合\n";
        $prompt .= "4. 提供具体的示例\n";
        $prompt .= "5. 循序渐进，由浅入深\n";
        $prompt .= "6. 确保输出是纯Markdown格式，不要包含markdown代码块标记\n";

        return $prompt;
    }

    /**
     * 解析大纲JSON
     */
    private function parseOutlineJSON(string $jsonString): array
    {
        // 清理可能的markdown代码块标记
        $jsonString = preg_replace('/^```json\s*/m', '', $jsonString);
        $jsonString = preg_replace('/^```\s*/m', '', $jsonString);
        $jsonString = preg_replace('/\s*```$/m', '', $jsonString);
        $jsonString = trim($jsonString);

        // 尝试提取JSON部分（如果有其他文字）
        if (preg_match('/\{[\s\S]*\}/', $jsonString, $matches)) {
            $jsonString = $matches[0];
        }

        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('JSON解析失败: ' . json_last_error_msg() . "\n原始内容：" . substr($jsonString, 0, 500));
        }

        return $data;
    }

    /**
     * 记录API调用日志
     */
    private function logAPICall(
        bool $success,
        array $request,
        ?array $response,
        ?string $error,
        float $duration,
        int $statusCode = 200,
        int $tokenUsed = 0
    ): void {
        try {
            ApiLog::create([
                'user_id' => $this->userId ?? 0,
                'api_type' => 'gemini',
                'endpoint' => $this->model,
                'request_data' => json_encode($request, JSON_UNESCAPED_UNICODE),
                'response_data' => $response ? json_encode($response, JSON_UNESCAPED_UNICODE) : null,
                'token_used' => $tokenUsed,
                'status_code' => $statusCode,
                'success' => $success ? 1 : 0,
                'error_message' => $error,
                'duration' => (int)$duration,
                'ip_address' => request()->ip(),
                'create_time' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            Log::error('API日志记录失败: ' . $e->getMessage());
        }
    }

    /**
     * 更新用户API配额
     */
    private function updateUserQuota(int $userId): void
    {
        try {
            $user = User::find($userId);
            if ($user) {
                $user->consumeQuota(1);
            }
        } catch (\Exception $e) {
            Log::error('更新用户配额失败: ' . $e->getMessage());
        }
    }

    /**
     * 检查用户配额
     */
    public function checkUserQuota(int $userId): bool
    {
        $user = User::find($userId);
        return $user && $user->hasQuota();
    }
}
