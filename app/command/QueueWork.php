<?php
declare(strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use app\index\model\GenerateTask;
use app\index\service\GenerateService;
use think\facade\Log;

/**
 * 队列任务处理命令
 * 执行: php think queue:work
 */
class QueueWork extends Command
{
    protected function configure()
    {
        $this->setName('queue:work')
            ->setDescription('处理生成任务队列');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('========================================');
        $output->writeln('  CourseGenius 队列任务处理器');
        $output->writeln('========================================');
        $output->writeln('队列任务开始运行...');
        $output->writeln('按 Ctrl+C 退出');
        $output->writeln('');

        $generateService = new GenerateService();
        $processCount = 0;

        while (true) {
            try {
                // 获取待处理任务（按优先级排序）
                $task = GenerateTask::where('status', GenerateTask::STATUS_PENDING)
                    ->orderRaw('priority DESC, id ASC')
                    ->find();

                if (!$task) {
                    // 没有任务，等待5秒
                    sleep(5);
                    continue;
                }

                $processCount++;
                $output->writeln('[' . date('Y-m-d H:i:s') . '] 开始处理任务 #' . $task->id . ' (' . $task->task_type . ')');

                // 更新状态为处理中
                $task->status = GenerateTask::STATUS_PROCESSING;
                $task->start_time = date('Y-m-d H:i:s');
                $task->save();

                // 处理任务
                if ($task->task_type === GenerateTask::TYPE_OUTLINE) {
                    // 处理大纲生成
                    $result = $generateService->processOutlineTask($task);
                } else {
                    // 处理内容生成
                    $result = $generateService->processContentTask($task);
                }

                // 更新任务状态
                if ($result['success']) {
                    $task->status = GenerateTask::STATUS_COMPLETED;
                    $task->result = $result['content'] ?? '';
                    $task->token_used = $result['token_used'] ?? 0;
                    $output->writeln('[' . date('Y-m-d H:i:s') . '] ✓ 任务 #' . $task->id . ' 完成 (Token: ' . $task->token_used . ')');
                } else {
                    $task->status = GenerateTask::STATUS_FAILED;
                    $task->error_message = $result['error'];
                    $task->retry_count += 1;

                    $output->writeln('[' . date('Y-m-d H:i:s') . '] ✗ 任务 #' . $task->id . ' 失败: ' . $result['error']);

                    // 如果失败次数少于3次，重新放回队列
                    if ($task->retry_count < 3) {
                        $task->status = GenerateTask::STATUS_PENDING;
                        $output->writeln('[' . date('Y-m-d H:i:s') . '] ⟳ 任务 #' . $task->id . ' 将重试 (第' . $task->retry_count . '次)');
                    }
                }

                $task->finish_time = date('Y-m-d H:i:s');
                $task->save();

                // 每处理10个任务输出统计
                if ($processCount % 10 === 0) {
                    $stats = $this->getQueueStats();
                    $output->writeln('');
                    $output->writeln('--- 队列统计 (已处理: ' . $processCount . ') ---');
                    $output->writeln('待处理: ' . $stats['pending']);
                    $output->writeln('处理中: ' . $stats['processing']);
                    $output->writeln('已完成: ' . $stats['completed']);
                    $output->writeln('失败: ' . $stats['failed']);
                    $output->writeln('');
                }

            } catch (\Exception $e) {
                $output->writeln('[' . date('Y-m-d H:i:s') . '] 错误: ' . $e->getMessage());
                Log::error('队列处理错误: ' . $e->getMessage());

                // 如果任务存在，标记为失败
                if (isset($task) && $task) {
                    $task->status = GenerateTask::STATUS_FAILED;
                    $task->error_message = $e->getMessage();
                    $task->finish_time = date('Y-m-d H:i:s');
                    $task->save();
                }

                // 发生错误后等待10秒再继续
                sleep(10);
            }
        }
    }

    /**
     * 获取队列统计
     */
    private function getQueueStats(): array
    {
        return [
            'pending' => GenerateTask::where('status', GenerateTask::STATUS_PENDING)->count(),
            'processing' => GenerateTask::where('status', GenerateTask::STATUS_PROCESSING)->count(),
            'completed' => GenerateTask::where('status', GenerateTask::STATUS_COMPLETED)->count(),
            'failed' => GenerateTask::where('status', GenerateTask::STATUS_FAILED)->count(),
        ];
    }
}
