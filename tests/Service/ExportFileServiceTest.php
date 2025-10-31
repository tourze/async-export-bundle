<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Service;

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Service\ExportFileService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @internal
 */
#[CoversClass(ExportFileService::class)]
final class ExportFileServiceTest extends TestCase
{
    private ExportFileService $service;

    private string $tempDir;

    protected function setUp(): void
    {
        // 创建临时目录
        $this->tempDir = sys_get_temp_dir() . '/async-export-test-' . uniqid();
        mkdir($this->tempDir, 0o755, true);

        $this->service = new ExportFileService($this->tempDir);
    }

    protected function tearDown(): void
    {
        // 清理临时文件
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testPrepareBatchDownload(): void
    {
        $result = $this->service->prepareBatchDownload([]);
        self::assertSame('empty', $result['type']);
    }

    public function testBatchDownloadWithEmptyArray(): void
    {
        $result = $this->service->prepareBatchDownload([]);
        self::assertSame('empty', $result['type']);
    }

    public function testBatchDownloadWithSingleTask(): void
    {
        $task = $this->createCompletedTask('test.csv');
        $this->createTestFile('test.csv');

        $result = $this->service->prepareBatchDownload([$task]);
        self::assertSame('single', $result['type']);
        self::assertArrayHasKey('path', $result);
    }

    public function testPrepareSingleFileSuccess(): void
    {
        $task = $this->createCompletedTask('test.csv');
        $this->createTestFile('test.csv');

        $filePath = $this->service->prepareSingleFile($task);
        self::assertNotNull($filePath);
        self::assertFileExists($filePath);
    }

    public function testPrepareSingleFileTaskNotCompleted(): void
    {
        $task = $this->createMock(AsyncExportTask::class);
        $task->method('isValid')->willReturn(false);

        $filePath = $this->service->prepareSingleFile($task);
        self::assertNull($filePath);
    }

    public function testPrepareSingleFileNoFileName(): void
    {
        $task = $this->createCompletedTask(null);

        $filePath = $this->service->prepareSingleFile($task);
        self::assertNull($filePath);
    }

    public function testCreateFileResponse(): void
    {
        $this->createTestFile('test.csv');
        $filePath = $this->tempDir . '/var/export/test.csv';

        $response = $this->service->createFileResponse($filePath, 'test.csv');
        self::assertFileExists($filePath);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testCreateZipResponse(): void
    {
        // 创建一个临时zip文件
        $zipPath = $this->tempDir . '/test.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $zip->addFromString('test.txt', 'test content');
        $zip->close();

        $response = $this->service->createZipResponse($zipPath);
        self::assertSame(200, $response->getStatusCode());
    }

    public function testBatchDownloadWithMultipleTasks(): void
    {
        $task1 = $this->createCompletedTask('test1.csv');
        $task2 = $this->createCompletedTask('test2.csv');
        $this->createTestFile('test1.csv');
        $this->createTestFile('test2.csv');

        $result = $this->service->prepareBatchDownload([$task1, $task2]);
        self::assertSame('zip', $result['type']);
        self::assertArrayHasKey('path', $result);
    }

    private function createCompletedTask(?string $fileName): AsyncExportTask
    {
        $task = $this->createMock(AsyncExportTask::class);
        $task->method('getFile')->willReturn($fileName);
        $task->method('isValid')->willReturn(true);
        $task->method('getTotalCount')->willReturn(100);
        $task->method('getProcessCount')->willReturn(100);

        return $task;
    }

    private function createTestFile(string $fileName): void
    {
        $exportDir = $this->tempDir . '/var/export';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0o755, true);
        }

        file_put_contents($exportDir . '/' . $fileName, 'test content');
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $scanResult = scandir($dir);
        if (false === $scanResult) {
            return;
        }

        $files = array_diff($scanResult, ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
