<?php

declare(strict_types=1);

namespace AsyncExportBundle\Command;

use AsyncExportBundle\Service\AsyncExportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'async-export:process',
    description: '处理异步导出任务'
)]
class ProcessExportTaskCommand extends Command
{
    public function __construct(
        private readonly AsyncExportService $exportService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('task-id', InputArgument::REQUIRED, '导出任务ID')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '强制重新处理已完成的任务')
            ->setHelp('处理指定的异步导出任务，生成实际的导出文件')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $taskIdArgument = $input->getArgument('task-id');
        if (!is_string($taskIdArgument) || !ctype_digit($taskIdArgument)) {
            $io->error('任务ID必须是正整数');

            return Command::FAILURE;
        }
        $taskId = (int) $taskIdArgument;

        if ($taskId <= 0) {
            $io->error('无效的任务ID');

            return Command::FAILURE;
        }

        $io->info(sprintf('开始处理导出任务: %d', $taskId));

        try {
            $success = $this->exportService->processExportTask($taskId);

            if ($success) {
                $io->success(sprintf('导出任务 %d 处理成功', $taskId));

                return Command::SUCCESS;
            }

            $io->error(sprintf('导出任务 %d 处理失败', $taskId));

            return Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error(sprintf('处理导出任务时发生异常: %s', $e->getMessage()));

            if ($output->isVeryVerbose()) {
                $io->writeln($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
