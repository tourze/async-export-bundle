<?php

declare(strict_types=1);

namespace AsyncExportBundle\Exception;

/**
 * 异步导出业务异常
 */
class AsyncExportException extends \RuntimeException
{
    public static function unexpectedRepositoryClass(string $class): self
    {
        return new self("Unexpected repository class: {$class}");
    }

    public static function taskNotFound(int $taskId): self
    {
        return new self("导出任务不存在: {$taskId}");
    }

    public static function emptyDql(): self
    {
        return new self('DQL查询语句为空');
    }

    public static function emptyFileName(): self
    {
        return new self('导出文件名为空');
    }

    public static function failedToCreateCsvFile(string $filePath): self
    {
        return new self("无法创建CSV文件: {$filePath}");
    }

    public static function exportDirectoryCreationFailed(string $dir): self
    {
        return new self("无法创建导出目录: {$dir}");
    }
}
