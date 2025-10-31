# AsyncExportableTrait 使用指南

## 概述

`AsyncExportableTrait` 是一个为 EasyAdmin CrudController 提供异步导出功能的 Trait。它能够根据当前页面的筛选条件、搜索条件和排序设置生成导出文件，支持 CSV 和 Excel 格式。

## 功能特性

- ✅ **应用当前筛选条件**：只导出页面当前显示的数据
- ✅ **支持搜索功能**：包含用户搜索的内容筛选
- ✅ **支持排序**：按照用户选择的排序方式导出
- ✅ **关联对象支持**：能正确处理 ManyToOne/OneToMany 等关联字段
- ✅ **数据格式化**：自动格式化日期、枚举、布尔值等类型
- ✅ **同步生成文件**：点击导出后立即生成可下载的文件
- ✅ **错误处理**：完整的异常处理和错误日志

## 基础用法

### 1. 引入 Trait

```php
<?php

namespace YourBundle\Controller\Admin;

use AsyncExportBundle\Trait\AsyncExportableTrait;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Doctrine\ORM\EntityManagerInterface;

class YourCrudController extends AbstractCrudController
{
    use AsyncExportableTrait;
    
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }
    
    // 必须实现的方法
    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
```

### 2. 实现必需的抽象方法

```php
/**
 * 创建导出查询构建器 - 必须实现
 */
protected function createExportQueryBuilder(AdminContext $context): QueryBuilder
{
    $entityRepository = $this->entityManager->getRepository(static::getEntityFqcn());
    $queryBuilder = $entityRepository->createQueryBuilder('entity');
    
    // 基础实现：只需要返回 QueryBuilder
    return $queryBuilder;
}
```

### 3. 配置导出按钮

```php
public function configureActions(Actions $actions): Actions
{
    return $actions
        ->add(Crud::PAGE_INDEX, Action::DETAIL)
        // ... 其他 actions
        
        // 使用 AsyncExportableTrait 的导出功能
        ->add(Crud::PAGE_INDEX, $this->configureAsyncExportActions());
}
```

### 4. 配置导出字段

```php
/**
 * 重写导出字段配置
 */
protected function getExportColumns(AdminContext $context = null): array
{
    return [
        'id' => 'ID',
        'name' => '名称',
        'email' => '邮箱',
        'createdAt' => '创建时间',
    ];
}
```

## 完整示例

以下是 `ReportCrudController` 的完整实现示例：

```php
<?php

namespace AIContentAuditBundle\Controller\Admin;

use AIContentAuditBundle\Entity\Report;
use AsyncExportBundle\Trait\AsyncExportableTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

final class ReportCrudController extends AbstractCrudController
{
    use AsyncExportableTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Report::class;
    }

    // 1. 实现必需的 EntityManager 获取方法
    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    // 2. 实现导出查询构建器（关键方法）
    protected function createExportQueryBuilder(AdminContext $context): \Doctrine\ORM\QueryBuilder
    {
        $entityRepository = $this->entityManager->getRepository(static::getEntityFqcn());
        $queryBuilder = $entityRepository->createQueryBuilder('entity');
        
        // 添加关联查询以支持导出关联字段
        $queryBuilder->leftJoin('entity.reportedContent', 'reportedContent')
                     ->addSelect('reportedContent');
        
        // 应用搜索条件
        $search = $context->getSearch();
        if ($search !== null && !empty($search->getQuery())) {
            $searchQuery = $search->getQuery();
            $searchFields = $this->getSearchableFields();
            
            if (!empty($searchFields)) {
                $orExpr = $queryBuilder->expr()->orX();
                foreach ($searchFields as $field) {
                    if ($field === 'reportedContent') {
                        $orExpr->add($queryBuilder->expr()->like("reportedContent.inputText", ':search'));
                    } else {
                        $orExpr->add($queryBuilder->expr()->like("entity.{$field}", ':search'));
                    }
                }
                $queryBuilder->andWhere($orExpr)
                    ->setParameter('search', '%' . $searchQuery . '%');
            }
        }

        // 应用筛选条件
        $request = $context->getRequest();
        $filtersData = $request->query->all('filters') ?? [];
        
        foreach ($filtersData as $filterName => $filterData) {
            if (!isset($filterData['value']) || $filterData['value'] === '' || $filterData['value'] === null) {
                continue;
            }
            
            $value = $filterData['value'];
            $comparison = $filterData['comparison'] ?? 'eq';
            
            // 根据字段类型处理筛选
            switch ($filterName) {
                case 'reporterUser':
                case 'reportReason':
                case 'processResult':
                    if ($comparison === 'contains' || $comparison === 'like') {
                        $queryBuilder->andWhere("entity.{$filterName} LIKE :{$filterName}")
                            ->setParameter($filterName, "%{$value}%");
                    } else {
                        $queryBuilder->andWhere("entity.{$filterName} = :{$filterName}")
                            ->setParameter($filterName, $value);
                    }
                    break;
                    
                case 'processStatus':
                    $queryBuilder->andWhere("entity.processStatus = :{$filterName}")
                        ->setParameter($filterName, $value);
                    break;
                    
                case 'reportTime':
                case 'processTime':
                    if (isset($filterData['value2'])) {
                        // 日期范围筛选
                        $queryBuilder->andWhere("entity.{$filterName} BETWEEN :{$filterName}_start AND :{$filterName}_end")
                            ->setParameter("{$filterName}_start", new \DateTime($value))
                            ->setParameter("{$filterName}_end", new \DateTime($filterData['value2']));
                    } else {
                        // 单一日期筛选
                        switch ($comparison) {
                            case 'after':
                            case 'gt':
                                $queryBuilder->andWhere("entity.{$filterName} > :{$filterName}")
                                    ->setParameter($filterName, new \DateTime($value));
                                break;
                            case 'before':
                            case 'lt':
                                $queryBuilder->andWhere("entity.{$filterName} < :{$filterName}")
                                    ->setParameter($filterName, new \DateTime($value));
                                break;
                            default:
                                $queryBuilder->andWhere("DATE(entity.{$filterName}) = DATE(:{$filterName})")
                                    ->setParameter($filterName, new \DateTime($value));
                        }
                    }
                    break;
                    
                case 'reportedContent':
                    $queryBuilder->andWhere("reportedContent.inputText LIKE :{$filterName}")
                        ->setParameter($filterName, "%{$value}%");
                    break;
            }
        }

        // 应用排序
        $sortData = $request->query->all('sort') ?? [];
        if (!empty($sortData)) {
            foreach ($sortData as $field => $direction) {
                $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
                $queryBuilder->addOrderBy("entity.{$field}", $direction);
            }
        } else {
            // 默认排序
            $queryBuilder->orderBy('entity.reportTime', 'DESC');
        }
        
        return $queryBuilder;
    }

    // 3. 配置导出字段
    protected function getExportColumns(AdminContext $context = null): array
    {
        return [
            'id' => 'ID',
            'reporterUser' => '举报用户',
            'reportedContent.inputText' => '被举报内容',  // 关联字段
            'reportTime' => '举报时间',
            'reportReason' => '举报理由',
            'processStatus' => '处理状态',              // 枚举字段
            'processTime' => '处理时间',
            'processResult' => '处理结果',
        ];
    }

    // 4. 配置导出按钮
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            // ... 其他 actions ...
            
            // 使用 AsyncExportableTrait 的导出功能
            ->add(Crud::PAGE_INDEX, $this->configureAsyncExportActions());
    }

    // 5. 配置搜索字段（可选）
    protected function getSearchableFields(): array
    {
        return ['reportReason', 'processResult', 'reporterUser', 'reportedContent'];
    }
}
```

## 高级用法

### 处理复杂的筛选条件

当你的实体有复杂的筛选需求时，可以在 `createExportQueryBuilder` 中添加更多逻辑：

```php
protected function createExportQueryBuilder(AdminContext $context): QueryBuilder
{
    $entityRepository = $this->entityManager->getRepository(static::getEntityFqcn());
    $queryBuilder = $entityRepository->createQueryBuilder('entity');
    
    // 添加多级关联
    $queryBuilder->leftJoin('entity.category', 'category')
                 ->leftJoin('entity.user', 'user')
                 ->addSelect('category', 'user');
    
    // 应用业务规则筛选
    $queryBuilder->andWhere('entity.status != :deleted')
                 ->setParameter('deleted', 'DELETED');
    
    return $queryBuilder;
}
```

### 自定义字段格式化

你可以重写 `formatCsvValue` 方法来自定义字段的格式化：

```php
protected function formatCsvValue(mixed $value): string
{
    // 自定义处理逻辑
    if ($value instanceof YourCustomClass) {
        return $value->getDisplayName();
    }
    
    // 调用父类方法处理其他类型
    return parent::formatCsvValue($value);
}
```

### 关联字段的处理

对于复杂的关联关系，建议使用点号分割的语法：

```php
protected function getExportColumns(AdminContext $context = null): array
{
    return [
        'id' => 'ID',
        'user.name' => '用户名称',               // OneToOne/ManyToOne 关联
        'user.profile.phone' => '用户电话',      // 多级关联
        'category.parent.name' => '父分类',      // 多级关联
        'tags.name' => '标签名称',              // OneToMany/ManyToMany 关联
    ];
}
```

## 数据类型支持

Trait 自动处理以下数据类型：

- **日期时间**: 自动格式化为 `Y-m-d H:i:s`
- **枚举类型**: 优先使用 `getLabel()` 方法，否则使用 `value` 属性
- **布尔值**: 转换为 "是/否"
- **对象**: 使用 `__toString()` 方法或类名
- **数组**: JSON 格式输出
- **null**: 空字符串

## 文件存储

导出的文件默认存储在：`{项目根目录}/var/export/{文件名}.csv`

可以在 `/admin/async/export-task` 页面查看和下载所有导出任务。

## 工作流程

1. **用户点击导出按钮**: 在列表页面点击"异步导出"按钮
2. **应用当前筛选条件**: 系统自动读取页面的搜索、筛选和排序条件
3. **同步生成文件**: 立即执行数据查询和文件生成（非异步）
4. **任务记录完成**: 将任务状态标记为已完成，可立即下载
5. **下载文件**: 用户可以在导出任务列表中下载完成的文件

## 注意事项

1. **内存使用**: 大量数据导出时注意内存限制，建议分批处理
2. **关联查询**: 使用 `leftJoin` 和 `addSelect` 来预加载关联数据，避免 N+1 问题
3. **筛选条件**: 确保所有筛选条件都在 `createExportQueryBuilder` 中正确处理
4. **字段映射**: 关联字段使用点号分割语法，如 `reportedContent.inputText`
5. **权限控制**: 导出功能会继承 CrudController 的权限设置

## 故障排除

### 导出数据为空或不正确
- 检查 `createExportQueryBuilder` 方法是否正确应用了筛选条件
- 确认关联查询使用了 `leftJoin` 和 `addSelect`
- 验证字段映射是否与实际实体属性匹配

### 关联字段显示空值
- 确保在查询中添加了相应的关联查询
- 检查字段名是否正确，使用 `entity.relatedField` 格式
- 验证关联对象确实存在数据

### 筛选条件不生效
- 确认 `createExportQueryBuilder` 中正确读取了筛选参数
- 检查参数名和比较操作符是否正确
- 调试时可以输出 DQL 来验证查询条件

这个 Trait 为 EasyAdmin 提供了强大而灵活的导出功能，能够满足大多数业务场景的需求。