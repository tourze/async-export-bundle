<?php

declare(strict_types=1);

namespace AsyncExportBundle\Service;

use AsyncExportBundle\Entity\AsyncExportTask;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 任务显示服务 - 处理格式化逻辑
 */
final class TaskDisplayService
{
    /**
     * 格式化用户显示值
     */
    public function formatUserValue(mixed $value): string
    {
        if ($value instanceof UserInterface && method_exists($value, 'getUsername')) {
            $username = $value->getUsername();

            return is_string($username) ? $username : '';
        }

        return ($value instanceof UserInterface) ? $value->getUserIdentifier() : '';
    }

    /**
     * 格式化文件信息显示
     */
    public function formatFileInfo(AsyncExportTask $entity): string
    {
        $fileName = $entity->getFile();
        if (null === $fileName || '' === $fileName) {
            return '未生成';
        }

        if ($this->isTaskCompleted($entity)) {
            return sprintf('%s <br><small><i class="fas fa-check-circle text-success"></i> 可下载</small>', $fileName);
        }

        return $fileName;
    }

    /**
     * 格式化字段配置信息显示
     */
    public function formatColumnsInfo(AsyncExportTask $entity): string
    {
        $columns = $entity->getColumns();
        if ([] === $columns) {
            return '未配置';
        }

        return $this->buildColumnsPreview($columns);
    }

    /**
     * 格式化任务状态显示
     */
    public function formatTaskStatus(AsyncExportTask $entity): string
    {
        if (true !== $entity->isValid()) {
            return '<span class="badge badge-danger">无效</span>';
        }

        $total = $entity->getTotalCount() ?? 0;
        $processed = $entity->getProcessCount() ?? 0;

        return $this->getStatusBadge($total, $processed);
    }

    /**
     * 格式化JSON数据显示
     */
    public function formatJsonData(mixed $data): string
    {
        if (null === $data) {
            return '{}';
        }
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return false !== $encoded ? $encoded : '{}';
    }

    /**
     * 格式化字节大小显示
     */
    public function formatBytes(mixed $value): string
    {
        $bytes = is_int($value) ? $value : 0;
        if (0 === $bytes) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = floor(log($bytes) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[(int) $pow];
    }

    /**
     * 检查任务是否已完成
     */
    public function isTaskCompleted(AsyncExportTask $task): bool
    {
        $isValid = $task->isValid();

        return true === $isValid
               && $task->getTotalCount() > 0
               && $task->getProcessCount() >= $task->getTotalCount();
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    private function buildColumnsPreview(array $columns): string
    {
        $count = count($columns);
        /** @var array<int|string, mixed> $fieldsRaw */
        $fieldsRaw = array_column($columns, 'field');
        $fields = array_slice($fieldsRaw, 0, 3);
        $preview = implode(', ', $fields);

        if ($count > 3) {
            $preview .= '...';
        }

        return sprintf('%d个字段: %s', $count, $preview);
    }

    private function getStatusBadge(int $total, int $processed): string
    {
        if ($total <= 0) {
            return '<span class="badge badge-warning">等待处理</span>';
        }

        if ($processed >= $total) {
            return '<span class="badge badge-success">已完成</span>';
        }

        $percentage = round(($processed / $total) * 100, 1);

        return sprintf('<span class="badge badge-info">进行中 %s%%</span>', $percentage);
    }
}
