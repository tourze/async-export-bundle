<?php

declare(strict_types=1);

namespace AsyncExportBundle\Controller\Admin;

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use AsyncExportBundle\Service\ExportFileService;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminAction;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @extends AbstractCrudController<AsyncExportTask>
 */
#[AdminCrud(routePath: '/async/export-task', routeName: 'async_export_task')]
final class AsyncExportTaskCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AsyncExportTaskRepository $asyncExportTaskRepository,
        private readonly ExportFileService $exportFileService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return AsyncExportTask::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $this->configureCrudSettings(
            $this->configureCrudLabels($crud)
        );
    }

    private function configureCrudLabels(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('导出任务')
            ->setEntityLabelInPlural('导出任务')
            ->setPageTitle('index', '导出任务管理')
            ->setPageTitle('detail', '导出任务详情')
        ;
    }

    private function configureCrudSettings(Crud $crud): Crud
    {
        return $crud
            ->setHelp('index', '查看和管理系统中的异步导出任务，可以查看导出状态、进度和下载完成的文件')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'file', 'entityClass', 'remark'])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return $this->buildFieldsArray($pageName);
    }

    /**
     * @return array<FieldInterface>
     */
    private function buildFieldsArray(string $pageName): array
    {
        return [
            ...$this->getBasicFields(),
            ...$this->getFileFields($pageName),
            ...$this->getProgressFields($pageName),
            ...$this->getMetaFields(),
            ...$this->getTimestampFields(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $this->setupActionsWithDefaults($actions);
    }

    private function setupActionsWithDefaults(Actions $actions): Actions
    {
        $downloadAction = $this->createDownloadAction();
        $batchDownloadAction = $this->createBatchDownloadAction();

        return $this->setupActions($actions, $downloadAction, $batchDownloadAction);
    }

    private function createDownloadAction(): Action
    {
        return Action::new('downloadFile', '下载', 'fas fa-download')
            ->linkToCrudAction('downloadFile')
            ->setCssClass('btn btn-success btn-sm')
            ->displayIf(fn (AsyncExportTask $task) => $this->isDownloadable($task))
        ;
    }

    private function isDownloadable(AsyncExportTask $task): bool
    {
        return true === $task->isValid()
               && null !== $task->getFile()
               && $task->getTotalCount() > 0
               && $task->getProcessCount() >= $task->getTotalCount();
    }

    private function createBatchDownloadAction(): Action
    {
        return Action::new('batchDownload', '批量下载完成文件', 'fas fa-download')
            ->linkToCrudAction('batchDownload')
            ->setCssClass('btn btn-primary')
            ->createAsGlobalAction()
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $this->setupFilters($filters);
    }

    /**
     * 下载导出文件
     */
    #[AdminAction(routeName: 'admin_async_export_download', routePath: '/admin/async-export/{entityId}/download')]
    public function downloadFile(AdminContext $context): Response
    {
        $task = $this->findTaskFromRequest($context);

        $filePath = $this->exportFileService->prepareSingleFile($task);
        if (null === $filePath) {
            $this->addFlash('danger', '文件尚未生成完成或不存在');
            throw $this->createNotFoundException('文件尚未生成完成');
        }

        return $this->exportFileService->createFileResponse($filePath, $task->getFile());
    }

    private function findTaskFromRequest(AdminContextInterface $context): AsyncExportTask
    {
        $taskId = $context->getRequest()->query->get('entityId');
        if (null === $taskId || '' === $taskId) {
            throw $this->createNotFoundException('任务ID不能为空');
        }

        $task = $this->asyncExportTaskRepository->find($taskId);
        if (null === $task) {
            throw $this->createNotFoundException('导出任务不存在');
        }

        return $task;
    }

    /**
     * 批量下载所有完成的文件
     */
    #[AdminAction(routeName: 'admin_async_export_batch_download', routePath: '/admin/async-export/batch-download')]
    public function batchDownload(AdminContext $context): Response
    {
        $completedTasks = $this->findCompletedTasks();
        $result = $this->exportFileService->prepareBatchDownload($completedTasks);

        if ('empty' === $result['type']) {
            $this->addFlash('warning', '没有找到可下载的完成任务');

            return $this->redirectToReferer($context);
        }

        if (!isset($result['path'])) {
            $this->addFlash('danger', '没有找到任何可下载的文件');

            return $this->redirectToReferer($context);
        }

        return match ($result['type']) {
            'single' => $this->exportFileService->createFileResponse($result['path'], $result['filename'] ?? null),
            'zip' => $this->exportFileService->createZipResponse($result['path']),
            default => $this->redirectToReferer($context),
        };
    }

    /**
     * @return array<AsyncExportTask>
     */
    private function findCompletedTasks(): array
    {
        /** @var array<AsyncExportTask> */
        return $this->asyncExportTaskRepository
            ->createQueryBuilder('t')
            ->where('t.valid = :valid')
            ->andWhere('t.totalCount > 0')
            ->andWhere('t.processCount >= t.totalCount')
            ->andWhere('t.file IS NOT NULL')
            ->setParameter('valid', true)
            ->orderBy('t.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    private function setupActions(Actions $actions, Action $downloadAction, Action $batchDownloadAction): Actions
    {
        return $this->configureActionPermissions(
            $this->addActionsToPages($actions, $downloadAction, $batchDownloadAction)
        );
    }

    private function addActionsToPages(Actions $actions, Action $downloadAction, Action $batchDownloadAction): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $downloadAction)
            ->add(Crud::PAGE_INDEX, $batchDownloadAction)
            ->add(Crud::PAGE_DETAIL, $downloadAction)
        ;
    }

    private function configureActionPermissions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->reorder(Crud::PAGE_INDEX, [Action::DETAIL, 'downloadFile'])
            ->setPermission(Action::DELETE, 'ROLE_SUPER_ADMIN')
        ;
    }

    private function setupFilters(Filters $filters): Filters
    {
        return $this->addNumericFilters(
            $this->addBasicFilters($filters)
        );
    }

    private function addBasicFilters(Filters $filters): Filters
    {
        return $filters
            ->add(EntityFilter::new('user', '用户'))
            ->add(TextFilter::new('file', '文件名'))
            ->add(TextFilter::new('entityClass', '实体类'))
            ->add(BooleanFilter::new('valid', '有效状态'))
        ;
    }

    private function addNumericFilters(Filters $filters): Filters
    {
        return $filters
            ->add(NumericFilter::new('totalCount', '总行数'))
            ->add(NumericFilter::new('processCount', '已处理'))
            ->add(DateTimeFilter::new('createTime', '创建时间'))
            ->add(DateTimeFilter::new('updateTime', '更新时间'))
        ;
    }

    private function formatUserValue(mixed $value): string
    {
        if (!($value instanceof UserInterface)) {
            return '';
        }

        return $this->extractUserDisplayName($value);
    }

    private function extractUserDisplayName(UserInterface $user): string
    {
        if (method_exists($user, 'getUsername')) {
            $username = $user->getUsername();
            if (is_string($username)) {
                return $username;
            }
        }

        return $user->getUserIdentifier();
    }

    /**
     * @return array<FieldInterface>
     */
    private function getFileFields(string $pageName): array
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return $this->getIndexFileFields();
        }

        return $this->getDetailFileFields();
    }

    /**
     * @return array<FieldInterface>
     */
    private function getDetailFields(): array
    {
        return [
            $this->createColumnsJsonField(),
            $this->createParametersJsonField(),
        ];
    }

    private function createColumnsJsonField(): FieldInterface
    {
        return TextareaField::new('columnsJson', '字段配置')
            ->setNumOfRows(10)
            ->formatValue(fn ($value, AsyncExportTask $entity) => $this->formatJsonData($entity->getColumns()))
            ->setHelp('导出字段的配置信息（JSON格式）')
        ;
    }

    private function createParametersJsonField(): FieldInterface
    {
        return TextareaField::new('jsonData', '参数信息')
            ->setNumOfRows(10)
            ->formatValue(fn ($value, AsyncExportTask $entity) => $this->formatJsonData($entity->getJson()))
            ->setHelp('导出任务的参数配置（JSON格式）')
        ;
    }

    /**
     * @return array<FieldInterface>
     */
    private function getProgressFields(string $pageName): array
    {
        if (Crud::PAGE_INDEX === $pageName) {
            return $this->getIndexProgressFields();
        }

        return $this->getDetailProgressFields();
    }

    /**
     * @return array<FieldInterface>
     */
    private function getMetaFields(): array
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
    private function getBasicFields(): array
    {
        return [
            ...$this->getIdentificationFields(),
            ...$this->getSystemFields(),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    private function getIdentificationFields(): array
    {
        return [
            IdField::new('id', 'ID')->setMaxLength(9999),
            AssociationField::new('user', '用户')
                ->formatValue(fn ($value) => $this->formatUserValue($value)),
            TextField::new('entityClass', '实体类')
                ->setHelp('导出数据的实体类名'),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    private function getSystemFields(): array
    {
        return [
            IntegerField::new('memoryUsage', '内存占用')
                ->formatValue(fn ($value) => $this->formatBytes($value ?? 0))
                ->setHelp('任务执行时的内存占用'),
            BooleanField::new('valid', '有效状态')->renderAsSwitch(false),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    private function getTimestampFields(): array
    {
        return [
            ...$this->getDateTimeFields(),
            ...$this->getAuditFields(),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    private function getDateTimeFields(): array
    {
        return [
            DateTimeField::new('createTime', '创建时间')
                ->hideOnForm()
                ->setFormat('yyyy-MM-dd HH:mm:ss'),
            DateTimeField::new('updateTime', '更新时间')
                ->hideOnForm()
                ->setFormat('yyyy-MM-dd HH:mm:ss'),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    private function getAuditFields(): array
    {
        return [
            TextField::new('createdBy', '创建人')->hideOnForm(),
            TextField::new('updatedBy', '更新人')->hideOnForm(),
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
        $preview = $this->createFieldsPreview($columns, $count);

        return sprintf('%d个字段: %s', $count, $preview);
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    private function createFieldsPreview(array $columns, int $count): string
    {
        $fields = array_slice(array_column($columns, 'field'), 0, 3);
        $preview = implode(', ', $fields);

        if ($count > 3) {
            $preview .= '...';
        }

        return $preview;
    }

    private function formatTaskStatus(AsyncExportTask $entity): string
    {
        if (true !== $entity->isValid()) {
            return $this->createInvalidBadge();
        }

        $total = $entity->getTotalCount() ?? 0;
        $processed = $entity->getProcessCount() ?? 0;

        return $this->getStatusBadge($total, $processed);
    }

    /**
     * @return array<FieldInterface>
     */
    private function getIndexFileFields(): array
    {
        return [
            TextField::new('file', '文件')
                ->formatValue(fn ($value, AsyncExportTask $entity) => $this->formatFileInfo($entity))
                ->setHelp('导出的文件名称和状态'),
            TextareaField::new('columnsInfo', '字段配置')
                ->formatValue(fn ($value, AsyncExportTask $entity) => $this->formatColumnsInfo($entity))
                ->setHelp('导出字段的配置概览')
                ->hideOnForm(),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    private function getDetailFileFields(): array
    {
        return [
            TextField::new('file', '文件名')->setHelp('导出的文件名称'),
            ...$this->getDetailFields(),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    private function getIndexProgressFields(): array
    {
        return [
            TextField::new('taskStatus', '任务状态')
                ->formatValue(fn ($value, AsyncExportTask $entity) => $this->formatTaskStatus($entity)),
        ];
    }

    /**
     * @return array<FieldInterface>
     */
    private function getDetailProgressFields(): array
    {
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

    private function createInvalidBadge(): string
    {
        return '<span class="badge badge-danger">无效</span>';
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

        if (false !== $encoded) {
            return $encoded;
        }

        return '{}';
    }

    /**
     * 格式化字节大小显示
     */
    private function formatBytes(mixed $value): string
    {
        $bytes = $this->normalizeBytes($value);

        if (0 === $bytes) {
            return '0 B';
        }

        return $this->calculateBytesWithUnit($bytes);
    }

    private function normalizeBytes(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        return 0;
    }

    private function calculateBytesWithUnit(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = $this->calculateUnitPower($bytes, count($units));
        $normalizedBytes = $bytes / pow(1024, $pow);

        return round($normalizedBytes, 2) . ' ' . $units[$pow];
    }

    private function calculateUnitPower(int $bytes, int $maxUnits): int
    {
        $pow = (int) floor(log($bytes) / log(1024));

        return min($pow, $maxUnits - 1);
    }

    /**
     * 检查任务是否已完成
     */
    private function isTaskCompleted(AsyncExportTask $task): bool
    {
        $isValid = $task->isValid();

        return true === $isValid
               && $task->getTotalCount() > 0
               && $task->getProcessCount() >= $task->getTotalCount();
    }

    /**
     * 重定向到引用页面
     */
    private function redirectToReferer(AdminContextInterface $context): Response
    {
        $referer = $context->getRequest()->headers->get('referer');
        $redirectUrl = is_string($referer) ? $referer : $this->generateUrl('admin');

        return $this->redirect($redirectUrl);
    }
}
