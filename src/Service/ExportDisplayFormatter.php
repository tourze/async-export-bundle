<?php

declare(strict_types=1);

namespace AsyncExportBundle\Service;

use AsyncExportBundle\Entity\AsyncExportTask;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Core\User\UserInterface;

final class ExportDisplayFormatter
{
    public function formatUserValue(mixed $value): string
    {
        if ($value instanceof UserInterface && method_exists($value, 'getUsername')) {
            $username = $value->getUsername();

            return is_string($username) ? $username : '';
        }

        return ($value instanceof UserInterface) ? $value->getUserIdentifier() : '';
    }

    /**
     * @return array<FieldInterface>
     */
    public function getFileFields(string $pageName): array
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return [
                TextField::new('file', '文件')
                    ->formatValue(fn ($value, AsyncExportTask $entity) => $this->formatFileInfo($entity))
                    ->setHelp('导出的文件名称和状态'),
                TextField::new('remark', '字段配置')
                    ->formatValue(fn ($value, AsyncExportTask $entity) => $this->formatColumnsInfo($entity))
                    ->setHelp('导出字段的配置概览'),
            ];
        }

        return [
            TextField::new('file', '文件名')->setHelp('导出的文件名称'),
            ...$this->getDetailFields(),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    public function getProgressFields(string $pageName): array
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return [
                BooleanField::new('valid', '任务状态')
                    ->formatValue(fn ($value, AsyncExportTask $entity) => $this->formatTaskStatus($entity)),
            ];
        }

        return [
            IntegerField::new('totalCount', '总行数')
                ->formatValue(function ($value): string {
                    return number_format(is_numeric($value) ? (float) $value : 0.0);
                })
                ->setHelp('需要导出的总行数'),
            IntegerField::new('processCount', '已处理')
                ->formatValue(function ($value): string {
                    return number_format(is_numeric($value) ? (float) $value : 0.0);
                })
                ->setHelp('已处理的行数'),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    public function getMetaFields(): array
    {
        return [
            TextareaField::new('remark', '备注')
                ->hideOnIndex()
                ->setMaxLength(100)
                ->setHelp('任务的备注说明'),
            TextareaField::new('exception', '异常信息')
                ->hideOnIndex()
                ->setMaxLength(200)
                ->setHelp('任务执行中的异常信息'),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    public function getTimestampFields(): array
    {
        return [
            DateTimeField::new('createTime', '创建时间')
                ->hideOnForm()
                ->setFormat('yyyy-MM-dd HH:mm:ss'),
            DateTimeField::new('updateTime', '更新时间')
                ->hideOnForm()
                ->setFormat('yyyy-MM-dd HH:mm:ss'),
            TextField::new('createdBy', '创建人')->hideOnForm(),
            TextField::new('updatedBy', '更新人')->hideOnForm(),
        ];
    }

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
     * @return array<FieldInterface>
     */
    private function getDetailFields(): array
    {
        return [
            TextareaField::new('columnsJson', '字段配置')
                ->setNumOfRows(10)
                ->formatValue(fn ($value, AsyncExportTask $entity) => $this->formatJsonData($entity->getColumns()))
                ->setHelp('导出字段的配置信息（JSON格式）'),
            TextareaField::new('jsonData', '参数信息')
                ->setNumOfRows(10)
                ->formatValue(fn ($value, AsyncExportTask $entity) => $this->formatJsonData($entity->getJson()))
                ->setHelp('导出任务的参数配置（JSON格式）'),
        ];
    }

    private function formatFileInfo(AsyncExportTask $entity): string
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

    private function formatColumnsInfo(AsyncExportTask $entity): string
    {
        $columns = $entity->getColumns();
        if ([] === $columns) {
            return '未配置';
        }

        return $this->buildColumnsPreview($columns);
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

    private function formatTaskStatus(AsyncExportTask $entity): string
    {
        if (true !== $entity->isValid()) {
            return '<span class="badge badge-danger">无效</span>';
        }

        $total = $entity->getTotalCount() ?? 0;
        $processed = $entity->getProcessCount() ?? 0;

        return $this->getStatusBadge($total, $processed);
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

    private function formatJsonData(mixed $data): string
    {
        if (null === $data) {
            return '{}';
        }
        $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return false !== $encoded ? $encoded : '{}';
    }

    private function isTaskCompleted(AsyncExportTask $task): bool
    {
        return true === $task->isValid()
               && $task->getTotalCount() > 0
               && $task->getProcessCount() >= $task->getTotalCount();
    }
}
