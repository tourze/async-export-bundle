<?php

declare(strict_types=1);

namespace AsyncExportBundle\Command;

use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use AsyncExportBundle\Service\AsyncExportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'async-export:process-all',
    description: '处理所有待处理的异步导出任务'
)]
class ProcessAllExportTasksCommand extends Command
{
    public function __construct(
        private readonly AsyncExportService $exportService,
        private readonly AsyncExportTaskRepository $taskRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, '处理任务的最大数量', '10')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制重新处理已完成的任务')
            ->setHelp('批量处理所有待处理的异步导出任务')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limitOption = $input->getOption('limit');
        if (!is_string($limitOption) || !ctype_digit($limitOption)) {
            $io->error('limit选项必须是正整数');

            return Command::FAILURE;
        }
        $limit = (int) $limitOption;
        $force = (bool) $input->getOption('force');

        if ($limit <= 0) {
            $io->error('limit必须大于0');

            return Command::FAILURE;
        }

        $io->info(sprintf('开始批量处理导出任务 (limit: %d)', $limit));

        // 获取待处理的任务
        $tasks = $this->taskRepository->findPendingTasks($limit, $force);

        if (0 === count($tasks)) {
            $io->info('没有待处理的导出任务');

            return Command::SUCCESS;
        }

        $io->progressStart(count($tasks));

        $successCount = 0;
        $failureCount = 0;

        foreach ($tasks as $task) {
            $taskId = $task->getId();
            if (null === $taskId) {
                ++$failureCount;
                $io->writeln(' ✗ 任务ID为空', OutputInterface::VERBOSITY_VERBOSE);
                $io->progressAdvance();
                continue;
            }

            try {
                $success = $this->exportService->processExportTask((int) $taskId);

                if ($success) {
                    ++$successCount;
                    $io->writeln(sprintf(' ✓ 任务 %s 处理成功', $taskId), OutputInterface::VERBOSITY_VERBOSE);
                } else {
                    ++$failureCount;
                    $io->writeln(sprintf(' ✗ 任务 %s 处理失败', $taskId), OutputInterface::VERBOSITY_VERBOSE);
                }
            } catch (\Throwable $e) {
                ++$failureCount;
                $io->writeln(sprintf(' ✗ 任务 %s 异常: %s', $taskId, $e->getMessage()), OutputInterface::VERBOSITY_VERBOSE);
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // 输出统计结果
        $io->table(
            ['状态', '数量'],
            [
                ['成功', $successCount],
                ['失败', $failureCount],
                ['总计', count($tasks)],
            ]
        );

        if ($failureCount > 0) {
            $io->warning(sprintf('有 %d 个任务处理失败', $failureCount));

            return Command::FAILURE;
        }

        $io->success(sprintf('成功处理 %d 个导出任务', $successCount));

        return Command::SUCCESS;
    }
}
