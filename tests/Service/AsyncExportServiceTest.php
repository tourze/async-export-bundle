<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Service;

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Exception\AsyncExportException;
use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use AsyncExportBundle\Service\AsyncExportService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Psr\Log\LoggerInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncExportService::class)]
#[RunTestsInSeparateProcesses]
final class AsyncExportServiceTest extends AbstractIntegrationTestCase
{
    private AsyncExportService $service;

    private AsyncExportTaskRepository $repository;

    private string $projectDir;

    protected function onSetUp(): void
    {
        // 从容器获取真实服务
        $this->repository = self::getService(AsyncExportTaskRepository::class);

        // 使用临时目录
        $this->projectDir = sys_get_temp_dir() . '/async-export-test-' . uniqid();

        // 创建服务实例并覆盖 projectDir 参数
        $container = self::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        $taskRepository = $container->get(AsyncExportTaskRepository::class);
        self::assertInstanceOf(AsyncExportTaskRepository::class, $taskRepository);
        $logger = $container->get(LoggerInterface::class);
        self::assertInstanceOf(LoggerInterface::class, $logger);

        $container->set(
            AsyncExportService::class,
            // @phpstan-ignore-next-line integrationTest.noDirectInstantiationOfCoveredClass
            new AsyncExportService(
                $entityManager,
                $taskRepository,
                $logger,
                $this->projectDir
            )
        );
        $this->service = self::getService(AsyncExportService::class);
    }

    protected function onTearDown(): void
    {
        // 清理导出文件
        if (is_dir($this->projectDir . '/var/export')) {
            $this->removeDirectory($this->projectDir . '/var/export');
        }
    }

    public function testProcessExportTaskWithNonExistentTask(): void
    {
        $this->expectException(AsyncExportException::class);
        $this->expectExceptionMessage('导出任务不存在: 999');

        $this->service->processExportTask(999);
    }

    public function testDebugSimpleExportTask(): void
    {
        // 创建一个极简的任务来调试问题
        $task = new AsyncExportTask();
        $task->setDql('SELECT u.id FROM AsyncExportBundle\Entity\AsyncExportTask u');
        $task->setEntityClass(AsyncExportTask::class);
        $task->setFile('debug-export.csv');
        $task->setColumns([
            ['property' => 'id', 'label' => 'ID', 'type' => 'number'],
        ]);
        $task->setValid(false);

        $this->persistAndFlush($task);

        $result = $this->service->processExportTask((int) $task->getId());

        // 如果结果是false，检查task的exception字段来了解错误
        if (!$result) {
            self::getEntityManager()->clear();
            $updatedTask = $this->repository->find($task->getId());
            $exception = $updatedTask?->getException() ?? 'No error message found';
            self::fail('Export task failed. Error: ' . $exception);
        }

        // @phpstan-ignore-next-line staticMethod.alreadyNarrowedType (已在上面检查过false的情况)
        self::assertTrue($result, 'Export task should succeed');
    }

    public function testProcessExportTaskWithValidTask(): void
    {
        // 创建真实任务实体
        $task = new AsyncExportTask();
        $task->setDql('SELECT u FROM AsyncExportBundle\Entity\AsyncExportTask u');
        $task->setEntityClass(AsyncExportTask::class);
        $task->setFile('test-export.csv');
        $task->setColumns([
            ['property' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['property' => 'valid', 'label' => '状态', 'type' => 'boolean'],
        ]);
        $task->setValid(false);

        // 持久化到真实数据库
        $this->persistAndFlush($task);

        // 执行导出
        $result = $this->service->processExportTask((int) $task->getId());

        // 验证结果
        self::assertTrue($result);

        // 从数据库重新加载任务验证状态
        self::getEntityManager()->clear();
        $updatedTask = $this->repository->find($task->getId());

        self::assertNotNull($updatedTask);
        self::assertTrue($updatedTask->isValid());
        self::assertGreaterThanOrEqual(1, $updatedTask->getTotalCount());
        self::assertEquals($updatedTask->getTotalCount(), $updatedTask->getProcessCount());
    }

    public function testProcessExportTaskCreatesRealFile(): void
    {
        $task = new AsyncExportTask();
        $task->setDql('SELECT u FROM AsyncExportBundle\Entity\AsyncExportTask u');
        $task->setFile('real-export.csv');
        $task->setColumns([
            ['property' => 'id', 'label' => 'ID', 'type' => 'string'],
        ]);

        $this->persistAndFlush($task);

        $this->service->processExportTask((int) $task->getId());

        // 验证文件真实存在
        $filePath = $this->projectDir . '/var/export/real-export.csv';
        self::assertFileExists($filePath);

        // 验证文件内容
        $content = file_get_contents($filePath);
        self::assertIsString($content);
        self::assertStringContainsString('ID', $content);
    }

    public function testProcessExportTaskWithComplexQuery(): void
    {
        // 首先创建一些测试数据
        $task1 = new AsyncExportTask();
        $task1->setDql('SELECT u FROM AsyncExportBundle\Entity\AsyncExportTask u');
        $task1->setEntityClass(AsyncExportTask::class);
        $task1->setFile('task1.csv');
        $task1->setColumns([
            ['property' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['property' => 'file', 'label' => 'File', 'type' => 'string'],
        ]);
        $task1->setValid(true);

        $task2 = new AsyncExportTask();
        $task2->setDql('SELECT u FROM AsyncExportBundle\Entity\AsyncExportTask u');
        $task2->setEntityClass(AsyncExportTask::class);
        $task2->setFile('task2.csv');
        $task2->setColumns([
            ['property' => 'id', 'label' => 'ID', 'type' => 'number'],
        ]);
        $task2->setValid(false);

        $this->persistAndFlush($task1);
        $this->persistAndFlush($task2);

        // 创建导出任务查询这些数据
        $exportTask = new AsyncExportTask();
        $exportTask->setDql('SELECT u FROM AsyncExportBundle\Entity\AsyncExportTask u WHERE u.valid = 1');
        $exportTask->setEntityClass(AsyncExportTask::class);
        $exportTask->setFile('complex-export.csv');
        $exportTask->setColumns([
            ['property' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['property' => 'file', 'label' => 'File Name', 'type' => 'string'],
            ['property' => 'valid', 'label' => 'Valid', 'type' => 'boolean'],
        ]);

        $this->persistAndFlush($exportTask);

        $result = $this->service->processExportTask((int) $exportTask->getId());

        self::assertTrue($result);

        // 验证文件内容包含正确的数据
        $filePath = $this->projectDir . '/var/export/complex-export.csv';
        self::assertFileExists($filePath);

        $content = file_get_contents($filePath);
        self::assertNotFalse($content, 'File content should be readable');
        self::assertStringContainsString('task1.csv', $content);
        self::assertStringNotContainsString('task2.csv', $content); // task2 is invalid, should not appear
    }

    public function testProcessMultipleExportTasksIndividually(): void
    {
        // 创建多个待处理任务
        $task1 = new AsyncExportTask();
        $task1->setDql('SELECT u FROM AsyncExportBundle\Entity\AsyncExportTask u');
        $task1->setEntityClass(AsyncExportTask::class);
        $task1->setFile('batch1.csv');
        $task1->setColumns([['property' => 'id', 'label' => 'ID', 'type' => 'number']]);
        $task1->setValid(false);

        $task2 = new AsyncExportTask();
        $task2->setDql('SELECT u FROM AsyncExportBundle\Entity\AsyncExportTask u');
        $task2->setEntityClass(AsyncExportTask::class);
        $task2->setFile('batch2.csv');
        $task2->setColumns([['property' => 'id', 'label' => 'ID', 'type' => 'number']]);
        $task2->setValid(false);

        $this->persistAndFlush($task1);
        $this->persistAndFlush($task2);

        // 分别处理每个任务
        $result1 = $this->service->processExportTask((int) $task1->getId());
        $result2 = $this->service->processExportTask((int) $task2->getId());

        // 验证处理结果
        self::assertTrue($result1);
        self::assertTrue($result2);

        // 验证任务状态已更新
        self::getEntityManager()->clear();
        $updatedTask1 = $this->repository->find($task1->getId());
        $updatedTask2 = $this->repository->find($task2->getId());

        self::assertNotNull($updatedTask1);
        self::assertNotNull($updatedTask2);
        self::assertTrue($updatedTask1->isValid());
        self::assertTrue($updatedTask2->isValid());

        // 验证文件都被创建
        self::assertFileExists($this->projectDir . '/var/export/batch1.csv');
        self::assertFileExists($this->projectDir . '/var/export/batch2.csv');
    }

    public function testProcessExportTaskHandlesErrorsGracefully(): void
    {
        // 创建一个带有无效DQL的任务
        $task = new AsyncExportTask();
        $task->setDql('SELECT invalid FROM NonExistentEntity e');
        $task->setEntityClass('NonExistentEntity');
        $task->setFile('error-test.csv');
        $task->setColumns([['property' => 'id', 'label' => 'ID', 'type' => 'number']]);
        $task->setValid(false);

        $this->persistAndFlush($task);

        // 执行应该处理错误并记录
        $result = $this->service->processExportTask((int) $task->getId());

        // 应该返回false表示失败
        self::assertFalse($result);

        // 任务状态应该保持为无效
        self::getEntityManager()->clear();
        $updatedTask = $this->repository->find($task->getId());

        self::assertNotNull($updatedTask);
        self::assertFalse($updatedTask->isValid());
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(false !== scandir($dir) ? scandir($dir) : [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
