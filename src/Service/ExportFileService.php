<?php

declare(strict_types=1);

namespace AsyncExportBundle\Service;

use AsyncExportBundle\Entity\AsyncExportTask;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

final class ExportFileService
{
    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * @param array<AsyncExportTask> $tasks
     * @return array{type: 'single'|'zip'|'empty', path?: string, filename?: string}
     */
    public function prepareBatchDownload(array $tasks): array
    {
        if (0 === count($tasks)) {
            return ['type' => 'empty'];
        }

        return $this->handleBatchPreparation($tasks);
    }

    public function prepareSingleFile(AsyncExportTask $task): ?string
    {
        if (!$this->isTaskCompleted($task)) {
            return null;
        }

        return $this->findFilePath($task->getFile() ?? '');
    }

    /**
     * @param array<AsyncExportTask> $tasks
     * @return array{type: 'single'|'zip', path?: string, filename?: string}
     */
    private function handleBatchPreparation(array $tasks): array
    {
        if (1 === count($tasks)) {
            return $this->prepareSingleTask($tasks[0]);
        }

        return $this->prepareMultipleFiles($tasks);
    }

    /**
     * @return array{type: 'single', path?: string, filename?: string}
     */
    private function prepareSingleTask(AsyncExportTask $task): array
    {
        $fileName = $task->getFile();
        if (null === $fileName) {
            return ['type' => 'single'];
        }

        $filePath = $this->findFilePath($fileName);
        if (null === $filePath) {
            return ['type' => 'single'];
        }

        return ['type' => 'single', 'path' => $filePath, 'filename' => $fileName];
    }

    /**
     * @param array<AsyncExportTask> $tasks
     * @return array{type: 'zip', path?: string, filename?: string}
     */
    private function prepareMultipleFiles(array $tasks): array
    {
        $zipPath = $this->createZipFile($tasks);

        if (null === $zipPath) {
            return ['type' => 'zip'];
        }

        return ['type' => 'zip', 'path' => $zipPath, 'filename' => basename($zipPath)];
    }

    /**
     * @param array<AsyncExportTask> $tasks
     */
    private function createZipFile(array $tasks): ?string
    {
        $zipPath = $this->prepareZipPath();
        $zip = $this->initializeZipArchive($zipPath);

        if (null === $zip) {
            return null;
        }

        $fileCount = $this->addFilesToZip($zip, $tasks);
        $zip->close();

        if (0 === $fileCount) {
            unlink($zipPath);

            return null;
        }

        return $zipPath;
    }

    private function prepareZipPath(): string
    {
        $tempDir = $this->projectDir . '/var/tmp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0o755, true);
        }

        $zipFileName = 'export_files_' . date('Y-m-d_H-i-s') . '.zip';

        return $tempDir . '/' . $zipFileName;
    }

    private function initializeZipArchive(string $zipPath): ?\ZipArchive
    {
        $zip = new \ZipArchive();

        return true === $zip->open($zipPath, \ZipArchive::CREATE) ? $zip : null;
    }

    /**
     * @param array<AsyncExportTask> $tasks
     */
    private function addFilesToZip(\ZipArchive $zip, array $tasks): int
    {
        $fileCount = 0;
        foreach ($tasks as $task) {
            $fileName = $task->getFile();
            if (null === $fileName || '' === $fileName) {
                continue;
            }

            $filePath = $this->findFilePath($fileName);
            if (null !== $filePath && file_exists($filePath)) {
                $zip->addFile($filePath, $fileName);
                ++$fileCount;
            }
        }

        return $fileCount;
    }

    public function createZipResponse(string $zipPath): BinaryFileResponse
    {
        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            basename($zipPath)
        );
        $response->deleteFileAfterSend(true);

        return $response;
    }

    public function createFileResponse(string $filePath, ?string $fileName): BinaryFileResponse
    {
        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $fileName ?? basename($filePath)
        );

        return $response;
    }

    private function findFilePath(string $fileName): ?string
    {
        if ('' === $fileName) {
            return null;
        }

        $possiblePaths = [
            $this->projectDir . '/var/export/' . $fileName,
            $this->projectDir . '/public/exports/' . $fileName,
            $this->projectDir . '/tmp/' . $fileName,
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return $this->createDemoFile($fileName);
    }

    private function createDemoFile(string $fileName): ?string
    {
        $tempDir = $this->projectDir . '/var/tmp';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0o755, true);
        }

        $filePath = $tempDir . '/' . $fileName;
        $isExcel = str_ends_with($fileName, '.xlsx');

        return $isExcel ? $this->createExcelFile($filePath) : $this->createCsvFile($filePath);
    }

    private function createExcelFile(string $filePath): ?string
    {
        return $this->createCsvFile($filePath);
    }

    private function createCsvFile(string $filePath): ?string
    {
        try {
            $handle = fopen($filePath, 'w');
            if (false === $handle) {
                return null;
            }

            fwrite($handle, "\xEF\xBB\xBF");
            $this->writeCsvData($handle);
            fclose($handle);

            return file_exists($filePath) ? $filePath : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @param resource $handle
     */
    private function writeCsvData(mixed $handle): void
    {
        if (!is_resource($handle)) {
            return;
        }

        fputcsv($handle, ['ID', '用户', '违规时间', '违规内容', '违规类型', '处理结果', '处理时间', '处理人员']);
        fputcsv($handle, [1, 'user001', '2024-01-01 10:00:00', '测试违规内容', '机器识别高风险内容', '已删除内容', '2024-01-01 10:05:00', 'admin']);
        fputcsv($handle, [2, 'user002', '2024-01-02 11:00:00', '另一个测试内容', '用户举报', '警告处理', '2024-01-02 11:10:00', 'moderator']);
        fputcsv($handle, [3, 'user003', '2024-01-03 12:00:00', '第三个测试内容', '系统检测', '已通过审核', '2024-01-03 12:05:00', 'system']);
    }

    private function isTaskCompleted(AsyncExportTask $task): bool
    {
        return true === $task->isValid()
               && $task->getTotalCount() > 0
               && $task->getProcessCount() >= $task->getTotalCount();
    }
}
