<?php

declare(strict_types=1);

namespace AsyncExportBundle\Service;

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Exception\AsyncExportException;
use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;

/**
 * 异步导出服务
 */
class AsyncExportService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AsyncExportTaskRepository $taskRepository,
        private readonly LoggerInterface $logger,
        private readonly string $projectDir,
    ) {
    }

    /**
     * 处理导出任务
     */
    public function processExportTask(int $taskId): bool
    {
        $task = $this->taskRepository->find($taskId);
        if (null === $task) {
            throw AsyncExportException::taskNotFound($taskId);
        }

        $taskIsValid = $task->isValid();
        if (true === $taskIsValid) {
            $this->logger->info('任务已完成，跳过处理', ['task_id' => $taskId]);

            return true;
        }

        try {
            $this->logger->info('开始处理导出任务', ['task_id' => $taskId]);

            // 获取数据
            $data = $this->fetchData($task);
            $totalCount = count($data);

            // 更新任务总数
            $task->setTotalCount($totalCount);
            $this->entityManager->flush();

            if (0 === $totalCount) {
                $task->setValid(true);
                $task->setProcessCount(0);
                $this->entityManager->flush();
                $this->logger->info('没有数据需要导出', ['task_id' => $taskId]);

                return true;
            }

            // 生成文件
            $filePath = $this->generateExportFile($task, $data);

            // 更新任务状态
            $task->setProcessCount($totalCount);
            $task->setValid(true);
            $task->setMemoryUsage(memory_get_peak_usage());
            $this->entityManager->flush();

            $this->logger->info('导出任务完成', [
                'task_id' => $taskId,
                'total_count' => $totalCount,
                'file_path' => $filePath,
            ]);

            return true;
        } catch (AsyncExportException $e) {
            // 业务异常直接抛出，不捕获
            throw $e;
        } catch (\Throwable $e) {
            $this->handleTaskError($task, $e);

            return false;
        }
    }

    /**
     * 获取数据
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchData(AsyncExportTask $task): array
    {
        $dql = $task->getDql();
        if (null === $dql) {
            throw AsyncExportException::emptyDql();
        }

        $query = $this->entityManager->createQuery($dql);

        /** @var array<int, array<string, mixed>> */
        return $query->getArrayResult();
    }

    /**
     * 生成导出文件
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function generateExportFile(AsyncExportTask $task, array $data): string
    {
        $fileName = $task->getFile();
        if (null === $fileName) {
            throw AsyncExportException::emptyFileName();
        }

        $exportDir = $this->projectDir . '/var/export';
        if (!is_dir($exportDir) && !mkdir($exportDir, 0o755, true)) {
            throw AsyncExportException::exportDirectoryCreationFailed($exportDir);
        }

        $filePath = $exportDir . '/' . $fileName;

        if (str_ends_with($fileName, '.xlsx')) {
            return $this->generateExcelFile($task, $data, $filePath);
        }

        return $this->generateCsvFile($task, $data, $filePath);
    }

    /**
     * 生成Excel文件
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function generateExcelFile(AsyncExportTask $task, array $data, string $filePath): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $columns = $task->getColumns();

        $this->setExcelHeaders($sheet, $columns);
        $this->fillExcelData($sheet, $data, $columns);
        $this->saveExcelFile($spreadsheet, $filePath);

        return $filePath;
    }

    /**
     * 生成CSV文件
     *
     * @param array<int, array<string, mixed>> $data
     */
    private function generateCsvFile(AsyncExportTask $task, array $data, string $filePath): string
    {
        $handle = $this->openCsvFile($filePath);
        $columns = $task->getColumns();

        $this->writeCsvHeaders($handle, $columns);
        $this->writeCsvData($handle, $data, $columns);

        fclose($handle);

        return $filePath;
    }

    /**
     * 获取属性值
     *
     * @param array<string, mixed> $item
     */
    private function getPropertyValue(array $item, string $property): mixed
    {
        // 支持点号分割的嵌套属性
        $keys = explode('.', $property);
        $value = $item;

        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * 格式化值
     */
    private function formatValue(mixed $value, string $type): string
    {
        if (null === $value) {
            return '';
        }

        return match ($type) {
            'datetime' => $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : $this->convertToString($value),
            'boolean' => (bool) $value ? '是' : '否',
            'number' => is_numeric($value) ? number_format((float) $value, 2) : $this->convertToString($value),
            default => $this->convertToString($value),
        };
    }

    /**
     * 安全转换任意值为字符串
     */
    private function convertToString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_array($value)) {
            return false !== json_encode($value, JSON_UNESCAPED_UNICODE)
                ? json_encode($value, JSON_UNESCAPED_UNICODE)
                : '';
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return '';
    }

    /**
     * @return resource
     */
    private function openCsvFile(string $filePath)
    {
        $handle = fopen($filePath, 'w');
        if (false === $handle) {
            throw AsyncExportException::failedToCreateCsvFile($filePath);
        }

        // 写入BOM以支持中文
        fwrite($handle, "\xEF\xBB\xBF");

        return $handle;
    }

    /**
     * @param resource $handle
     * @param array<int, array<string, mixed>> $columns
     */
    private function writeCsvHeaders($handle, array $columns): void
    {
        $headers = $this->extractHeaders($columns);
        fputcsv($handle, $headers, ',', '"', '\\');
    }

    /**
     * @param resource $handle
     * @param array<int, array<string, mixed>> $data
     * @param array<int, array<string, mixed>> $columns
     */
    private function writeCsvData($handle, array $data, array $columns): void
    {
        foreach ($data as $item) {
            $rowData = $this->buildRowData($item, $columns);
            /** @var array<string> $rowData */
            fputcsv($handle, $rowData, ',', '"', '\\');
        }
    }

    /**
     * @param Worksheet $sheet
     * @param array<int, array<string, mixed>> $columns
     */
    private function setExcelHeaders($sheet, array $columns): void
    {
        $headers = $this->extractHeaders($columns);
        $sheet->fromArray([$headers], null, 'A1');
    }

    /**
     * @param Worksheet $sheet
     * @param array<int, array<string, mixed>> $data
     * @param array<int, array<string, mixed>> $columns
     */
    private function fillExcelData($sheet, array $data, array $columns): void
    {
        $rowIndex = 2;
        foreach ($data as $item) {
            $rowData = $this->buildRowData($item, $columns);
            $sheet->fromArray([$rowData], null, 'A' . $rowIndex);
            ++$rowIndex;
        }
    }

    private function saveExcelFile(Spreadsheet $spreadsheet, string $filePath): void
    {
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     * @return array<string>
     */
    private function extractHeaders(array $columns): array
    {
        $headers = [];
        foreach ($columns as $column) {
            $label = $column['label'] ?? $column['property'] ?? 'Unknown';
            $headers[] = is_string($label) ? $label : 'Unknown';
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<int, array<string, mixed>> $columns
     * @return array<string>
     */
    private function buildRowData(array $item, array $columns): array
    {
        $rowData = [];
        foreach ($columns as $column) {
            $propertyValue = $column['property'] ?? '';
            $property = is_string($propertyValue) ? $propertyValue : '';

            $value = $this->getPropertyValue($item, $property);

            $typeValue = $column['type'] ?? 'string';
            $type = is_string($typeValue) ? $typeValue : 'string';

            $rowData[] = $this->formatValue($value, $type);
        }

        return $rowData;
    }

    /**
     * 处理任务错误
     */
    private function handleTaskError(AsyncExportTask $task, \Throwable $e): void
    {
        $errorMessage = $this->buildErrorMessage($e);
        $this->updateTaskWithError($task, $errorMessage);
        $this->logTaskError($task, $e);
    }

    private function buildErrorMessage(\Throwable $e): string
    {
        return sprintf(
            '导出任务失败: %s (文件: %s, 行: %d)',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        );
    }

    private function updateTaskWithError(AsyncExportTask $task, string $errorMessage): void
    {
        $task->setException($errorMessage);
        $task->setValid(false);

        try {
            $this->entityManager->flush();
        } catch (\Throwable $flushException) {
            $this->logger->error('保存任务错误信息失败', [
                'task_id' => $task->getId(),
                'original_error' => $errorMessage,
                'flush_error' => $flushException->getMessage(),
            ]);
        }
    }

    private function logTaskError(AsyncExportTask $task, \Throwable $e): void
    {
        $this->logger->error('导出任务失败', [
            'task_id' => $task->getId(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
