<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Command;

use AsyncExportBundle\Command\ProcessExportTaskCommand;
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
#[CoversClass(ProcessExportTaskCommand::class)]
#[RunTestsInSeparateProcesses]
final class ProcessExportTaskCommandTest extends AbstractCommandTestCase
{
    /** 测试用的默认任务ID（验证命令行为） */
    private int $defaultTaskId = 123;

    /** 验证命令的真实名称（非 Mock） */
    private string $expectedCommandName = 'async-export:process';

    /** 验证命令的真实描述（非 Mock） */
    private string $expectedCommandDescription = '处理异步导出任务';

    /** @var array<int, true> 用于跟踪已处理的任务ID */
    private array $processedTasks = [];

    protected function onSetUp(): void
    {
        // 初始化计数器
        $this->processedTasks = [];
    }

    /**
     * 创建并设置 Mock 服务（供需要 Mock 的测试使用）
     *
     * @return MockObject&AsyncExportService
     */
    private function setupMockService(): MockObject
    {
        $mockExportService = $this->createMock(AsyncExportService::class);

        // 设置测试替身服务到容器
        $container = self::getContainer();
        $container->set('AsyncExportBundle\Service\AsyncExportService', $mockExportService);

        return $mockExportService;
    }

    protected function getCommandTester(): CommandTester
    {
        $command = self::getService(ProcessExportTaskCommand::class);

        return new CommandTester($command);
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

    public function testCommandName(): void
    {
        $command = self::getService(ProcessExportTaskCommand::class);
        self::assertSame($this->expectedCommandName, $command->getName());
    }

    public function testDefaultTaskId(): void
    {
        self::assertSame(123, $this->defaultTaskId);
        self::assertGreaterThan(0, $this->defaultTaskId);
    }

    public function testCommandDescription(): void
    {
        $command = self::getService(ProcessExportTaskCommand::class);
        self::assertSame($this->expectedCommandDescription, $command->getDescription());
    }

    public function testExecuteWithInvalidTaskId(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $result = $commandTester->execute(['task-id' => '0']);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testArgumentTaskId(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setProcessExportTaskResult($mockService, true);

        $result = $commandTester->execute(['task-id' => '123']);

        self::assertSame(Command::SUCCESS, $result);
        self::assertTrue($this->wasTaskProcessed(123));
    }

    public function testExecuteWithNonNumericTaskId(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $result = $commandTester->execute(['task-id' => 'abc']);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testExecuteWithNegativeTaskId(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $result = $commandTester->execute(['task-id' => '-1']);

        self::assertSame(Command::FAILURE, $result);
    }

    public function testExecuteWithSuccessfulProcessing(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setProcessExportTaskResult($mockService, true);

        $result = $commandTester->execute(['task-id' => '123']);

        self::assertSame(Command::SUCCESS, $result);
        self::assertTrue($this->wasTaskProcessed(123));

        // 验证真实的命令输出（非 Mock 行为）
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('成功', $output);
    }

    public function testExecuteWithFailedProcessing(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setProcessExportTaskResult($mockService, false);

        $result = $commandTester->execute(['task-id' => '123']);

        self::assertSame(Command::FAILURE, $result);
        self::assertTrue($this->wasTaskProcessed(123));
    }

    public function testExecuteWithException(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setShouldThrowException($mockService, true, new \RuntimeException('Test exception'));

        $result = $commandTester->execute(['task-id' => '123'], ['verbosity' => 256]);

        self::assertSame(Command::FAILURE, $result);
        self::assertTrue($this->wasTaskProcessed(123));
    }

    public function testExecuteWithExceptionNonVerbose(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setShouldThrowException($mockService, true, new \RuntimeException('Test exception'));

        $result = $commandTester->execute(['task-id' => '123']);

        self::assertSame(Command::FAILURE, $result);
        self::assertTrue($this->wasTaskProcessed(123));
    }

    public function testExecuteWithLargeTaskId(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setProcessExportTaskResult($mockService, true);

        $result = $commandTester->execute(['task-id' => '2147483647']);

        self::assertSame(Command::SUCCESS, $result);
        self::assertTrue($this->wasTaskProcessed(2147483647));
    }

    public function testExecuteWithLeadingZeros(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setProcessExportTaskResult($mockService, true);

        $result = $commandTester->execute(['task-id' => '00123']);

        self::assertSame(Command::SUCCESS, $result);
        self::assertTrue($this->wasTaskProcessed(123));
    }

    public function testOptionForce(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setProcessExportTaskResult($mockService, true);

        $result = $commandTester->execute(['task-id' => '123', '--force' => true]);

        self::assertSame(Command::SUCCESS, $result);
        self::assertTrue($this->wasTaskProcessed(123));
    }

    /**
     * 验证命令的真实配置属性（非 Mock）
     */
    public function testCommandConfiguration(): void
    {
        $command = self::getService(ProcessExportTaskCommand::class);

        // 验证命令定义的真实属性
        $definition = $command->getDefinition();

        // 验证 task-id 参数
        self::assertTrue($definition->hasArgument('task-id'));
        $taskIdArgument = $definition->getArgument('task-id');
        self::assertTrue($taskIdArgument->isRequired());
        self::assertSame('导出任务ID', $taskIdArgument->getDescription());

        // 验证 force 选项
        self::assertTrue($definition->hasOption('force'));
        $forceOption = $definition->getOption('force');
        self::assertSame('f', $forceOption->getShortcut());
        self::assertFalse($forceOption->acceptValue());

        // 验证命令帮助文本
        self::assertSame('处理指定的异步导出任务，生成实际的导出文件', $command->getHelp());
    }

    /**
     * 验证命令输出格式（非 Mock 行为）
     */
    public function testCommandOutputFormat(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setProcessExportTaskResult($mockService, true);

        $commandTester->execute(['task-id' => '456']);

        $output = $commandTester->getDisplay();

        // 验证真实的输出内容
        self::assertStringContainsString('开始处理导出任务: 456', $output);
        self::assertStringContainsString('导出任务 456 处理成功', $output);
    }

    /**
     * 验证命令失败时的输出格式（非 Mock 行为）
     */
    public function testCommandOutputFormatOnFailure(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setProcessExportTaskResult($mockService, false);

        $commandTester->execute(['task-id' => '789']);

        $output = $commandTester->getDisplay();

        // 验证真实的失败输出内容
        self::assertStringContainsString('开始处理导出任务: 789', $output);
        self::assertStringContainsString('导出任务 789 处理失败', $output);
    }

    /**
     * 验证异常时的详细输出（非 Mock 行为）
     */
    public function testCommandVerboseExceptionOutput(): void
    {
        $mockService = $this->setupMockService();
        $commandTester = $this->getCommandTester();

        $this->setShouldThrowException($mockService, true, new \RuntimeException('Test exception message'));

        $commandTester->execute(['task-id' => '999'], ['verbosity' => 256]); // VERBOSITY_VERY_VERBOSE

        $output = $commandTester->getDisplay();

        // 验证真实的异常输出内容
        self::assertStringContainsString('处理导出任务时发生异常', $output);
        self::assertStringContainsString('Test exception message', $output);
        self::assertStringContainsString('#0', $output); // 堆栈跟踪应该出现
    }
}
