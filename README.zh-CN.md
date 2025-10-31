# 异步导出模块

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/async-export-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/async-export-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/async-export-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/async-export-bundle)
[![License](https://img.shields.io/packagist/l/tourze/async-export-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/async-export-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/async-export-bundle/ci.yml?branch=master&style=flat-square)](https://github.com/tourze/async-export-bundle/actions)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/async-export-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/async-export-bundle)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/tourze/async-export-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/async-export-bundle/?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/async-export-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/async-export-bundle)

用于管理异步数据导出任务的 Symfony 模块，支持 DQL 查询、进度跟踪和基于用户的任务管理。

## 功能特性

- **异步处理**: 处理大量数据导出而不阻塞主应用程序
- **DQL 查询支持**: 使用 Doctrine 查询语言进行灵活强大的数据查询
- **进度跟踪**: 实时监控导出进度，包含总数和已处理数量
- **用户管理**: 将导出任务与认证用户关联以确保安全
- **灵活配置**: 基于 JSON 的字段配置，支持自定义导出
- **错误处理**: 全面的异常跟踪和错误报告
- **内存监控**: 跟踪内存使用情况以优化性能
- **任务验证**: 内置验证和状态管理系统
- **审计跟踪**: 完整的时间戳和用户归属跟踪

## 安装

```bash
composer require tourze/async-export-bundle
```

## 系统要求

- PHP 8.1 或更高版本
- Symfony 6.4 或更高版本
- Doctrine ORM 3.0 或更高版本
- Doctrine DBAL 4.0 或更高版本

## 快速开始

```php
<?php

use AsyncExportBundle\Entity\AsyncExportTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

// 创建新的导出任务
$task = new AsyncExportTask();
$task->setDql('SELECT u FROM App\Entity\User u WHERE u.active = true');
$task->setEntityClass('App\Entity\User');
$task->setColumns(['id', 'email', 'name', 'createTime']);
$task->setFile('users_export.csv');
$task->setRemark('导出活跃用户');
$task->setUser($security->getUser()); // 关联当前用户
$task->setValid(true);

// 保存任务
$entityManager->persist($task);
$entityManager->flush();

// 任务现在可以进行后台处理
// 使用 Symfony Messenger 或类似工具进行异步执行
```

## 配置

### 模块注册

在 `config/bundles.php` 中添加模块：

```php
<?php

return [
    // ...
    AsyncExportBundle\AsyncExportBundle::class => ['all' => true],
];
```

### 服务配置

模块会自动在 `services.yaml` 中配置服务：

```yaml
# config/services.yaml 或在您的模块中
services:
    AsyncExportBundle\Repository\:
        resource: '../src/Repository/'
```

### 实体映射

确保 Doctrine 配置为扫描模块实体：

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        mappings:
            AsyncExportBundle:
                type: attribute
                dir: '%kernel.project_dir%/vendor/tourze/async-export-bundle/src/Entity'
                prefix: 'AsyncExportBundle\Entity'
```

## 实体结构

`AsyncExportTask` 实体提供全面的导出任务管理：

### 核心属性

- **`user`**: 关联用户 (UserInterface)
- **`file`**: 导出文件路径 (字符串，最大 1000 字符)
- **`entityClass`**: 目标实体类名，用于 EntityManager 查找
- **`dql`**: 用于数据选择的 Doctrine 查询语言字符串
- **`columns`**: 定义导出列配置的 JSON 数组

### 跟踪属性

- **`totalCount`**: 要导出的记录总数
- **`processCount`**: 已处理的记录数
- **`memoryUsage`**: 处理过程中的内存消耗
- **`valid`**: 任务验证状态标志

### 元数据属性

- **`remark`**: 人类可读的任务描述
- **`exception`**: 失败任务的错误消息存储
- **`json`**: 额外参数存储

### 继承的特性

- **SnowflakeKeyAware**: 唯一ID生成
- **TimestampableAware**: 创建/更新时间戳
- **BlameableAware**: 用户跟踪审计踪迹

## 仓储使用

```php
<?php

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use Doctrine\ORM\EntityManagerInterface;

// 获取仓储
$repository = $entityManager->getRepository(AsyncExportTask::class);

// 根据用户查找任务
$userTasks = $repository->findBy(['user' => $user]);

// 查找有效/待处理任务
$validTasks = $repository->findBy(['valid' => true]);

// 根据实体类查找任务
$entityTasks = $repository->findBy(['entityClass' => 'App\Entity\User']);

// 查找有进度跟踪的任务
$incompleteTasks = $repository->createQueryBuilder('t')
    ->where('t.processCount < t.totalCount')
    ->andWhere('t.valid = true')
    ->getQuery()
    ->getResult();
```

## 高级用法

### 进度跟踪

```php
// 在处理过程中更新任务进度
$task->setTotalCount(1000);
$task->setProcessCount(250); // 25% 完成
$task->setMemoryUsage(memory_get_usage());
$entityManager->flush();
```

### 错误处理

```php
try {
    // 处理导出
    processExport($task);
} catch (\Exception $e) {
    $task->setException($e->getMessage());
    $task->setValid(false);
    $entityManager->flush();
}
```

## 安全

### 访问控制

该模块与 Symfony 的安全组件集成，以确保适当的访问控制：

- 所有导出任务都与认证用户关联
- 用户只能访问自己的导出任务
- 任务创建需要有效的身份验证
- 应验证文件路径以防止目录遍历

### 数据保护

- 导出文件应存储在安全位置
- DQL 查询中的敏感数据应正确转义
- 建议定期清理已完成的导出文件
- 内存使用情况跟踪有助于防止资源耗尽

### 最佳实践

- 始终验证 DQL 查询的用户输入
- 实施适当的文件权限控制
- 使用安全的文件命名约定
- 监控导出任务执行是否异常
- 为导出任务创建实施速率限制

## 贡献

请查看 [CONTRIBUTING.md](CONTRIBUTING.md) 了解详情。

## 许可证

MIT 许可证（MIT）。请查看 [License File](LICENSE) 了解更多信息。
