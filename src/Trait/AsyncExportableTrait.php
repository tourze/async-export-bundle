<?php

declare(strict_types=1);

namespace AsyncExportBundle\Trait;

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Exception\AsyncExportException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * 为EasyAdmin CrudController提供异步导出功能的Trait
 */
trait AsyncExportableTrait
{
    /**
     * 获取实体管理器
     */
    abstract protected function getEntityManager(): EntityManagerInterface;

    /**
     * 创建导出查询构建器，子类需要实现此方法来支持当前页面的筛选条件
     */
    abstract protected function createExportQueryBuilder(AdminContextInterface $context): mixed;

    /**
     * 配置导出Action
     */
    public function configureAsyncExportActions(): Action
    {
        return Action::new('asyncExport', '同步导出')
            ->linkToCrudAction('triggerAsyncExport')
            ->setIcon('fa fa-download')
            ->setHtmlAttributes(['title' => '同步导出当前筛选数据'])
            ->setCssClass('btn btn-info')
            ->createAsGlobalAction()
        ;
    }

    /**
     * 触发异步导出
     */
    public function triggerAsyncExport(AdminContextInterface $context): Response
    {
        // 获取当前用户
        $user = $this->getUser();
        if (null === $user) {
            $this->addFlash('danger', '请先登录');

            return $this->redirectToRoute('admin');
        }

        try {
            // 创建导出任务，传递 AdminContext 以获取当前页面的筛选条件
            $task = $this->createAsyncExportTask($user, $context);

            // 立即处理导出任务（同步生成文件）
            $this->processExportTaskSync($task, $context);

            // 保存任务
            $this->getEntityManager()->persist($task);
            $this->getEntityManager()->flush();

            $this->addFlash('success', sprintf(
                '同步导出已完成！任务ID: %s，文件名: %s。请前往导出任务页面下载文件。',
                $task->getId(),
                $task->getFile()
            ));
        } catch (\Exception $e) {
            $this->addFlash('danger', '创建导出任务失败：' . $e->getMessage());
        }

        // 重定向到当前列表页面，使用安全的重定向方式
        $redirectUrl = $this->getRedirectUrl($context);

        return $this->redirect($redirectUrl);
    }

    /**
     * 创建异步导出任务
     */
    protected function createAsyncExportTask(UserInterface $user, AdminContextInterface $context): AsyncExportTask
    {
        // 获取实体类名
        $entityClass = static::getEntityFqcn();

        // 使用 AdminContext 中的搜索和筛选条件创建查询
        $queryBuilder = $this->createExportQueryBuilder($context);

        // 获取DQL
        $query = $queryBuilder->getQuery();
        $dql = $query->getDQL();
        if (null === $dql || '' === $dql) {
            throw AsyncExportException::emptyDql();
        }

        // 获取字段配置
        $columns = $this->getExportColumns($context);

        // 创建任务
        $task = new AsyncExportTask();
        $task->setEntityClass($entityClass);
        $task->setDql($dql);
        $task->setColumns($columns);
        $task->setUser($user);
        $task->setRemark($this->getExportRemark($context));
        $task->setFile($this->generateExportFileName($context));
        $task->setJson($this->getExportParameters($context));

        return $task;
    }

    /**
     * 获取导出字段配置
     * 子类可以重写此方法来提供自定义的字段配置
     * @return array<int, array<string, mixed>>
     */
    protected function getExportColumns(?AdminContextInterface $context = null): array
    {
        // 默认字段配置，子类可以重写此方法
        return [
            ['field' => 'id', 'label' => 'ID', 'type' => 'string'],
        ];
    }

    /**
     * 获取字段属性名
     */
    protected function getFieldPropertyName(mixed $field): string
    {
        if (!is_object($field)) {
            return 'id';
        }

        // 尝试标准方法
        $property = $this->tryStandardMethods($field);
        if (null !== $property) {
            return $property;
        }

        // 尝试反射获取属性
        $property = $this->tryReflectionProperties($field);
        if (null !== $property) {
            return $property;
        }

        return 'id'; // 默认返回安全值
    }

    /**
     * 尝试标准的获取属性名方法
     */
    private function tryStandardMethods(object $field): ?string
    {
        if (method_exists($field, 'getProperty')) {
            $property = $field->getProperty();
            if (null !== $property && '' !== $property && is_string($property)) {
                return $property;
            }
        }

        if (method_exists($field, 'getPropertyName')) {
            $property = $field->getPropertyName();
            if (null !== $property && '' !== $property && is_string($property)) {
                return $property;
            }
        }

        return null;
    }

    /**
     * 尝试使用反射获取属性名
     */
    private function tryReflectionProperties(object $field): ?string
    {
        try {
            $reflection = new \ReflectionClass($field);

            // 检查常见的属性名
            $properties = ['propertyName', 'property'];
            foreach ($properties as $propName) {
                if ($reflection->hasProperty($propName)) {
                    $property = $reflection->getProperty($propName);
                    $property->setAccessible(true);
                    $value = $property->getValue($field);
                    if (null !== $value && '' !== $value && is_string($value)) {
                        return $value;
                    }
                }
            }

            // 检查 DTO 中的属性名
            return $this->tryDtoPropertyName($reflection, $field);
        } catch (\Exception $e) {
            // 反射失败，返回 null
        }

        return null;
    }

    /**
     * 尝试从 DTO 获取属性名
     * @param \ReflectionClass<object> $reflection
     */
    private function tryDtoPropertyName(\ReflectionClass $reflection, object $field): ?string
    {
        if (!$reflection->hasProperty('dto')) {
            return null;
        }

        try {
            $dtoProperty = $reflection->getProperty('dto');
            $dtoProperty->setAccessible(true);
            $dto = $dtoProperty->getValue($field);

            if (is_object($dto) && method_exists($dto, 'getPropertyName')) {
                $value = $dto->getPropertyName();
                if (null !== $value && '' !== $value && is_string($value)) {
                    return $value;
                }
            }
        } catch (\Exception $e) {
            // 忽略异常
        }

        return null;
    }

    /**
     * 获取字段标签
     */
    protected function getFieldLabel(mixed $field): ?string
    {
        if (!is_object($field)) {
            return null;
        }

        if (method_exists($field, 'getLabel')) {
            $label = $field->getLabel();

            return is_string($label) ? $label : null;
        }

        // 使用反射获取标签
        try {
            $reflection = new \ReflectionClass($field);
            if ($reflection->hasProperty('label')) {
                $property = $reflection->getProperty('label');
                $property->setAccessible(true);
                $value = $property->getValue($field);

                return is_string($value) ? $value : null;
            }
        } catch (\Exception $e) {
            // 反射失败，返回 null
        }

        return null;
    }

    /**
     * 判断字段是否应该跳过导出
     */
    protected function shouldSkipFieldForExport(mixed $field): bool
    {
        if (!is_object($field)) {
            return true;
        }

        // 跳过某些字段类型
        $skipTypes = ['ImagePreviewField', 'AvatarField', 'ActionField'];
        $fieldClass = get_class($field);

        foreach ($skipTypes as $skipType) {
            if (str_contains($fieldClass, $skipType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 获取字段导出类型
     */
    protected function getFieldExportType(mixed $field): string
    {
        if (!is_object($field)) {
            return 'string';
        }

        $fieldClass = get_class($field);

        if (str_contains($fieldClass, 'DateTimeField')) {
            return 'datetime';
        }

        if (str_contains($fieldClass, 'NumberField') || str_contains($fieldClass, 'IntegerField') || str_contains($fieldClass, 'IdField')) {
            return 'number';
        }

        if (str_contains($fieldClass, 'BooleanField')) {
            return 'boolean';
        }

        return 'string';
    }

    /**
     * 获取导出任务备注
     */
    protected function getExportRemark(?AdminContextInterface $context = null): string
    {
        $entityClass = static::getEntityFqcn();
        $lastSlashPos = strrpos($entityClass, '\\');
        $className = false !== $lastSlashPos ? substr($entityClass, $lastSlashPos + 1) : $entityClass;

        return sprintf(
            '%s数据导出 - %s',
            $className,
            date('Y-m-d H:i:s')
        );
    }

    /**
     * 生成导出文件名
     */
    protected function generateExportFileName(?AdminContextInterface $context = null): string
    {
        $entityClass = static::getEntityFqcn();
        $lastSlashPos = strrpos($entityClass, '\\');
        $className = false !== $lastSlashPos ? substr($entityClass, $lastSlashPos + 1) : $entityClass;

        return sprintf(
            '%s_export_%s.xlsx',
            strtolower($className),
            date('YmdHis')
        );
    }

    /**
     * 获取导出参数
     * @return array<string, mixed>
     */
    protected function getExportParameters(?AdminContextInterface $context = null): array
    {
        return [
            'format' => 'xlsx',
            'created_at' => date('Y-m-d H:i:s'),
            'entity_class' => static::getEntityFqcn(),
        ];
    }

    /**
     * 获取重定向URL
     */
    protected function getRedirectUrl(?AdminContextInterface $context = null): string
    {
        // 尝试从请求中获取 referer
        if (null !== $context) {
            $request = $context->getRequest();
            $referer = $request->headers->get('referer');
            if (null !== $referer && !str_contains($referer, 'triggerAsyncExport')) {
                return $referer;
            }
        }

        // 尝试获取当前请求路径
        try {
            $pathInfo = $this->getCurrentPathInfo();
            if (null !== $pathInfo) {
                return $pathInfo;
            }
        } catch (\Throwable) {
            // 忽略错误，使用回退方案
        }

        // 最后回退方案：使用通用的 admin 路由
        return $this->generateUrl('admin');
    }

    /**
     * 生成EasyAdmin URL - 避免生成会导致EntityDto问题的URL
     */
    /**
     * @param array<string, mixed> $parameters
     */
    protected function generateEaUrl(string $action = 'index', array $parameters = []): string
    {
        // 获取当前请求的路径，用于重定向
        try {
            $pathInfo = $this->getCurrentPathInfo();
            if (null !== $pathInfo) {
                // 返回当前页面路径，去掉查询参数
                return $pathInfo;
            }
        } catch (\Throwable) {
            // 忽略错误，使用回退方案
        }

        // 如果无法获取当前路径，使用通用的 admin 路由
        return $this->generateUrl('admin');
    }

    /**
     * 获取当前请求路径信息
     */
    private function getCurrentPathInfo(): ?string
    {
        try {
            // 直接尝试调用 getContext，如果不存在会抛出异常
            $currentContext = $this->getContext();
            if (null === $currentContext) {
                return null;
            }

            $request = $currentContext->getRequest();
            $pathInfo = $request->getPathInfo();

            // 确保路径信息是有效的字符串
            if ('' === $pathInfo) {
                return null;
            }

            return $pathInfo;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 同步处理导出任务（立即生成文件）
     */
    protected function processExportTaskSync(AsyncExportTask $task, AdminContextInterface $context): void
    {
        try {
            // 获取数据 - 使用对象模式以支持关联对象
            $queryBuilder = $this->createExportQueryBuilder($context);
            $query = $queryBuilder->getQuery();
            $result = $query->getResult(); // 使用对象模式而不是数组模式

            // 确保结果是数组类型
            if (!is_array($result)) {
                throw new AsyncExportException('查询结果必须是数组类型');
            }
            $data = $result;

            $totalCount = count($data);

            // 更新任务统计
            $task->setTotalCount($totalCount);
            $task->setProcessCount($totalCount);

            if ($totalCount > 0) {
                // 创建导出文件
                $this->generateExportFileSync($task, $data);
            }

            // 标记任务为已完成
            $task->setValid(true);
            $task->setMemoryUsage(memory_get_peak_usage());
        } catch (\Throwable $e) {
            // 处理错误
            $task->setException('导出失败: ' . $e->getMessage());
            $task->setValid(false);
            throw $e;
        }
    }

    /**
     * 同步生成导出文件
     * @param array<mixed> $data
     */
    protected function generateExportFileSync(AsyncExportTask $task, array $data): void
    {
        $fileName = $task->getFile();
        if (null === $fileName) {
            throw AsyncExportException::emptyFileName();
        }

        // 确保导出目录存在
        $projectDir = $this->getParameter('kernel.project_dir');
        if (!is_string($projectDir)) {
            throw new AsyncExportException('无法获取项目根目录');
        }

        $exportDir = $projectDir . '/var/export';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0o755, true);
        }

        $filePath = $exportDir . '/' . $fileName;

        // 生成CSV文件
        $this->generateCsvFileSync($task, $data, $filePath);
    }

    /**
     * 同步生成CSV文件
     * @param array<mixed> $data
     */
    protected function generateCsvFileSync(AsyncExportTask $task, array $data, string $filePath): void
    {
        $handle = $this->openCsvFile($filePath);

        // 写入BOM以支持中文
        fwrite($handle, "\xEF\xBB\xBF");

        $columns = $task->getColumns();

        // 写入表头和数据
        $this->writeCsvHeaders($handle, $columns);
        $this->writeCsvData($handle, $columns, $data);

        fclose($handle);
    }

    /**
     * 打开CSV文件
     * @return resource
     */
    private function openCsvFile(string $filePath)
    {
        $handle = fopen($filePath, 'w');
        if (false === $handle) {
            throw AsyncExportException::failedToCreateCsvFile($filePath);
        }

        return $handle;
    }

    /**
     * 写入CSV表头
     * @param resource $handle
     * @param array<int, array<string, mixed>> $columns
     */
    private function writeCsvHeaders($handle, array $columns): void
    {
        $headers = array_column($columns, 'label');
        $csvHeaders = array_map(fn ($header) => $this->normalizeCsvHeader($header), $headers);
        fputcsv($handle, $csvHeaders);
    }

    /**
     * 规范化CSV表头值
     */
    private function normalizeCsvHeader(mixed $header): string|int|float|bool|null
    {
        if (is_scalar($header) || null === $header) {
            return $header;
        }

        // 对于非标量类型，尝试转换为字符串
        if (is_object($header) && method_exists($header, '__toString')) {
            return (string) $header;
        }

        return '';
    }

    /**
     * 写入CSV数据
     * @param resource $handle
     * @param array<int, array<string, mixed>> $columns
     * @param array<mixed> $data
     */
    private function writeCsvData($handle, array $columns, array $data): void
    {
        foreach ($data as $item) {
            $rowData = $this->buildCsvRow($columns, $item);
            fputcsv($handle, $rowData);
        }
    }

    /**
     * 构建CSV行数据
     * @param array<int, array<string, mixed>> $columns
     * @return list<int|string|bool|float|null>
     */
    private function buildCsvRow(array $columns, mixed $item): array
    {
        $rowData = [];
        foreach ($columns as $column) {
            if (!isset($column['field'])) {
                continue;
            }
            $property = $column['field'];
            if (!is_string($property)) {
                continue;
            }
            $value = $this->getPropertyValueFromObject($item, $property);
            $rowData[] = $this->formatCsvValue($value);
        }

        return $rowData;
    }

    /**
     * 从对象中获取属性值
     */
    protected function getPropertyValueFromObject(mixed $item, string $property): mixed
    {
        // 支持点号分割的嵌套属性
        $keys = explode('.', $property);
        $value = $item;

        foreach ($keys as $key) {
            $value = $this->extractValueByKey($value, $key);
            if (null === $value) {
                return null;
            }
        }

        return $value;
    }

    /**
     * 根据键从值中提取数据
     */
    private function extractValueByKey(mixed $value, string $key): mixed
    {
        if (is_object($value)) {
            return $this->extractFromObject($value, $key);
        }

        if (is_array($value)) {
            return $this->extractFromArray($value, $key);
        }

        return null;
    }

    /**
     * 从对象中提取值
     */
    private function extractFromObject(object $value, string $key): mixed
    {
        // 尝试使用 getter 方法
        $getter = 'get' . ucfirst($key);
        if (method_exists($value, $getter) && is_callable([$value, $getter])) {
            /** @var callable(): mixed $callback */
            $callback = [$value, $getter];

            return call_user_func($callback);
        }

        // 尝试使用反射获取属性值
        return $this->extractFromObjectByReflection($value, $key);
    }

    /**
     * 从数组中提取值
     * @param array<mixed> $value
     */
    private function extractFromArray(array $value, string $key): mixed
    {
        return array_key_exists($key, $value) ? $value[$key] : null;
    }

    /**
     * 使用反射从对象中提取属性值
     */
    private function extractFromObjectByReflection(object $value, string $key): mixed
    {
        try {
            $reflection = new \ReflectionClass($value);
            if (!$reflection->hasProperty($key)) {
                return null;
            }

            $property = $reflection->getProperty($key);
            $property->setAccessible(true);

            return $property->getValue($value);
        } catch (\ReflectionException $e) {
            return null;
        }
    }

    /**
     * 从数组中获取属性值（保留向后兼容）
     * @param array<mixed> $item
     */
    protected function getPropertyValueFromArray(array $item, string $property): mixed
    {
        // 支持点号分割的嵌套属性
        $keys = explode('.', $property);
        $value = $item;

        foreach ($keys as $key) {
            $value = $this->extractValueByKey($value, $key);
            if (null === $value) {
                return null;
            }
        }

        return $value;
    }

    /**
     * 格式化CSV值
     */
    protected function formatCsvValue(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $this->formatDateTime($value);
        }

        if (is_bool($value)) {
            return $this->formatBoolean($value);
        }

        if (is_array($value)) {
            return $this->formatArray($value);
        }

        if ($value instanceof \BackedEnum) {
            return $this->formatEnum($value);
        }

        if (is_object($value)) {
            return $this->formatObject($value);
        }

        // 确保可以安全转换为字符串
        if (is_scalar($value)) {
            return (string) $value;
        }

        // 对于其他类型，返回类型描述
        return sprintf('[%s]', gettype($value));
    }

    /**
     * 格式化日期时间值
     */
    private function formatDateTime(\DateTimeInterface $dateTime): string
    {
        return $dateTime->format('Y-m-d H:i:s');
    }

    /**
     * 格式化布尔值
     */
    private function formatBoolean(bool $value): string
    {
        return $value ? '是' : '否';
    }

    /**
     * 格式化数组值
     * @param array<mixed> $value
     */
    private function formatArray(array $value): string
    {
        $result = json_encode($value, JSON_UNESCAPED_UNICODE);

        return false !== $result ? $result : '';
    }

    /**
     * 格式化枚举值
     */
    private function formatEnum(\BackedEnum $value): string
    {
        // 如果枚举有 getLabel 方法，优先使用
        if (method_exists($value, 'getLabel')) {
            /** @var callable(): mixed $callback */
            $callback = [$value, 'getLabel'];
            $label = call_user_func($callback);
            // 确保返回值是字符串类型
            if (is_string($label)) {
                return $label;
            }
            // 如果不是字符串，转换为字符串
            if (is_scalar($label) || null === $label) {
                return (string) $label;
            }
        }

        // 将枚举值转换为字符串
        $enumValue = $value->value;

        return (string) $enumValue;
    }

    /**
     * 格式化对象值
     */
    private function formatObject(object $value): string
    {
        // 如果对象有 __toString 方法
        if (method_exists($value, '__toString')) {
            return (string) $value;
        }

        // 否则返回类名
        return get_class($value);
    }
}
