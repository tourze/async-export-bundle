<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Command;

use AsyncExportBundle\Command\ProcessAllExportTasksCommand;
use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use AsyncExportBundle\Service\AsyncExportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(ProcessAllExportTasksCommand::class)]
#[RunTestsInSeparateProcesses]
final class ProcessAllExportTasksCommandTest extends AbstractCommandTestCase
{
    /** 用于测试的默认任务限制（验证命令配置） */
    private int $defaultTaskLimit = 10;

    /** 验证命令的真实名称（非 Mock） */
    private string $expectedCommandName = 'async-export:process-all';

    /** 验证命令的真实描述（非 Mock） */
    private string $expectedCommandDescription = '处理所有待处理的异步导出任务';

    /** @var array<int, true> 用于跟踪已处理的任务ID */
    private array $processedTasks = [];

    /** 已处理任务计数器 */
    private int $processedTaskCount = 0;

    protected function onSetUp(): void
    {
        // 初始化计数器
        $this->processedTasks = [];
        $this->processedTaskCount = 0;
    }

    /**
     * 创建并设置 Mock 服务（供需要 Mock 的测试使用）
     *
     * @return array{exportService: MockObject&AsyncExportService, taskRepository: MockObject&AsyncExportTaskRepository}
     */
    private function setupMockServices(): array
    {
        $mockExportService = $this->createMock(AsyncExportService::class);
        $mockTaskRepository = $this->createMock(AsyncExportTaskRepository::class);

        // 设置测试替身服务到容器
        $container = self::getContainer();
        $container->set('AsyncExportBundle\Service\AsyncExportService', $mockExportService);
        $container->set('AsyncExportBundle\Repository\AsyncExportTaskRepository', $mockTaskRepository);

        return [
            'exportService' => $mockExportService,
            'taskRepository' => $mockTaskRepository,
        ];
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(ProcessAllExportTasksCommand::class);

        return new CommandTester($command);
    }

    /**
     * 设置repository findPendingTasks方法的返回结果
     *
     * @param MockObject&AsyncExportTaskRepository $mockRepository
     * @param AsyncExportTask[] $tasks
     */
    private function setPendingTasksResult(MockObject $mockRepository, int $limit, bool $includeCompleted, array $tasks): void
    {
        $mockRepository
            ->method('findPendingTasks')
            ->with($limit, $includeCompleted)
            ->willReturn($tasks)
        ;
    }

    /**
     * 设置processExportTask方法的Mock行为
     *
     * @param MockObject&AsyncExportService $mockService
     */
    private function setProcessExportTaskResult(MockObject $mockService, bool $result): void
    {
        $mockService
            ->method('processExportTask')
            ->willReturnCallback(function (int $taskId) use ($result): bool {
                $this->processedTasks[$taskId] = true;
                ++$this->processedTaskCount;

                return $result;
            })
        ;
    }

    /**
     * 设置processExportTask方法抛出异常
     *
     * @param MockObject&AsyncExportService $mockService
     */
    private function setShouldThrowException(MockObject $mockService, bool $shouldThrow, \Throwable $exception): void
    {
        if ($shouldThrow) {
            $mockService
                ->method('processExportTask')
                ->willReturnCallback(function (int $taskId) use ($exception): bool {
                    $this->processedTasks[$taskId] = true;
                    throw $exception;
                })
            ;
        }
    }

    /**
     * 检查指定任务是否已被处理
     */
    private function wasTaskProcessed(int $taskId): bool
    {
        return isset($this->processedTasks[$taskId]);
    }

    /**
     * 获取已处理任务数量
     */
    private function getProcessedTaskCount(): int
    {
        return $this->processedTaskCount;
    }

    /**
     * 创建测试任务
     */
    private function createTestTask(int $id): AsyncExportTask
    {
        $task = new AsyncExportTask();
        // 使用反射设置私有属性ID
        $reflection = new \ReflectionClass($task);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($task, $id);

        return $task;
    }

    public function testCommandName(): void
    {
        $command = self::getService(ProcessAllExportTasksCommand::class);
        self::assertSame($this->expectedCommandName, $command->getName());
    }

    public function testDefaultTaskLimit(): void
    {
        self::assertSame(10, $this->defaultTaskLimit);
        self::assertGreaterThan(0, $this->defaultTaskLimit);
    }

    public function testCommandDescription(): void
    {
        $command = self::getService(ProcessAllExportTasksCommand::class);
        self::assertSame($this->expectedCommandDescription, $command->getDescription());
    }

    public function testExecuteWithInvalidLimit(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        $result = $commandTester->execute(['--limit' => '0']);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testOptionLimit(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        $this->setPendingTasksResult($mocks['taskRepository'], 5, false, []);

        $result = $commandTester->execute(['--limit' => '5']);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithNonNumericLimit(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        $result = $commandTester->execute(['--limit' => 'abc']);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testExecuteWithNoTasks(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        $this->setPendingTasksResult($mocks['taskRepository'], $this->defaultTaskLimit, false, []);

        $result = $commandTester->execute(['--limit' => (string) $this->defaultTaskLimit]);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testExecuteWithSuccessfulTasks(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        // 创建测试任务
        $task1 = $this->createTestTask(1);
        $task2 = $this->createTestTask(2);
        $tasks = [$task1, $task2];

        $this->setPendingTasksResult($mocks['taskRepository'], 2, false, $tasks);
        $this->setProcessExportTaskResult($mocks['exportService'], true);

        $result = $commandTester->execute(['--limit' => '2']);

        self::assertSame(Command::SUCCESS, $result);
        self::assertTrue($this->wasTaskProcessed(1));
        self::assertTrue($this->wasTaskProcessed(2));
        self::assertSame(2, $this->getProcessedTaskCount());

        // 验证真实的命令输出（非 Mock 行为）
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('成功', $output);
    }

    public function testExecuteWithFailedTasks(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        // 创建测试任务
        $task1 = $this->createTestTask(1);
        $task2 = $this->createTestTask(2);
        $tasks = [$task1, $task2];

        $this->setPendingTasksResult($mocks['taskRepository'], 2, false, $tasks);

        // 模拟第一个成功，第二个失败
        $callCount = 0;
        $mocks['exportService']
            ->method('processExportTask')
            ->willReturnCallback(function (int $taskId) use (&$callCount): bool {
                $this->processedTasks[$taskId] = true;
                ++$callCount;

                return 1 === $callCount; // 第一次成功，第二次失败
            })
        ;

        $result = $commandTester->execute(['--limit' => '2']);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testExecuteWithException(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        // 创建测试任务
        $task1 = $this->createTestTask(1);
        $tasks = [$task1];

        $this->setPendingTasksResult($mocks['taskRepository'], 1, false, $tasks);
        $this->setShouldThrowException($mocks['exportService'], true, new \RuntimeException('Test exception'));

        $result = $commandTester->execute(['--limit' => '1']);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testExecuteWithForceOption(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        $this->setPendingTasksResult($mocks['taskRepository'], 5, true, []);

        $result = $commandTester->execute(['--limit' => '5', '--force' => true]);

        self::assertSame(Command::SUCCESS, $result);
    }

    public function testOptionForce(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        $this->setPendingTasksResult($mocks['taskRepository'], $this->defaultTaskLimit, true, []);

        $result = $commandTester->execute(['--force' => true]);

        self::assertSame(Command::SUCCESS, $result);
    }

    /**
     * 验证命令的真实配置属性（非 Mock）
     */
    public function testCommandConfiguration(): void
    {
        $command = self::getService(ProcessAllExportTasksCommand::class);

        // 验证命令定义的真实属性
        $definition = $command->getDefinition();

        // 验证 limit 选项
        self::assertTrue($definition->hasOption('limit'));
        $limitOption = $definition->getOption('limit');
        self::assertSame('l', $limitOption->getShortcut());
        self::assertTrue($limitOption->isValueRequired());
        self::assertSame('10', $limitOption->getDefault());

        // 验证 force 选项
        self::assertTrue($definition->hasOption('force'));
        $forceOption = $definition->getOption('force');
        self::assertSame('f', $forceOption->getShortcut());
        self::assertFalse($forceOption->acceptValue());

        // 验证命令帮助文本
        self::assertSame('批量处理所有待处理的异步导出任务', $command->getHelp());
    }

    /**
     * 验证命令输出格式（非 Mock 行为）
     */
    public function testCommandOutputFormatWithNoTasks(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        $this->setPendingTasksResult($mocks['taskRepository'], 10, false, []);

        $commandTester->execute(['--limit' => '10']);

        $output = $commandTester->getDisplay();

        // 验证真实的输出内容
        self::assertStringContainsString('开始批量处理导出任务', $output);
        self::assertStringContainsString('没有待处理的导出任务', $output);
    }

    /**
     * 验证命令的统计表格输出（非 Mock 行为）
     */
    public function testCommandOutputStatisticsTable(): void
    {
        $mocks = $this->setupMockServices();
        $commandTester = $this->getCommandTester();

        $task1 = $this->createTestTask(1);
        $task2 = $this->createTestTask(2);
        $tasks = [$task1, $task2];

        $this->setPendingTasksResult($mocks['taskRepository'], 2, false, $tasks);
        $this->setProcessExportTaskResult($mocks['exportService'], true);

        $commandTester->execute(['--limit' => '2']);

        $output = $commandTester->getDisplay();

        // 验证统计表格的真实输出
        self::assertStringContainsString('状态', $output);
        self::assertStringContainsString('数量', $output);
        self::assertStringContainsString('成功', $output);
        self::assertStringContainsString('失败', $output);
        self::assertStringContainsString('总计', $output);
    }
}
