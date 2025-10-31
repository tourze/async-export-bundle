# Async Export Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/async-export-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/async-export-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/async-export-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/async-export-bundle)
[![License](https://img.shields.io/packagist/l/tourze/async-export-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/async-export-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/async-export-bundle/ci.yml?branch=master&style=flat-square)](https://github.com/tourze/async-export-bundle/actions)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/async-export-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/async-export-bundle)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/tourze/async-export-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/async-export-bundle/?branch=master)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/async-export-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/async-export-bundle)

A Symfony bundle for managing asynchronous data export tasks with DQL support,
progress tracking, and user-based task management.

## Features

- **Asynchronous Processing**: Handle large data exports without blocking the main application
- **DQL Query Support**: Use Doctrine Query Language for flexible and powerful data queries
- **Progress Tracking**: Real-time monitoring of export progress with total and processed counts
- **User Management**: Associate export tasks with authenticated users for security
- **Flexible Configuration**: JSON-based column configuration for customizable exports
- **Error Handling**: Comprehensive exception tracking and error reporting
- **Memory Monitoring**: Track memory usage to optimize performance
- **Task Validation**: Built-in validation and status management system
- **Audit Trail**: Full tracking with timestamps and user attribution

## Installation

```bash
composer require tourze/async-export-bundle
```

## Requirements

- PHP 8.1 or higher
- Symfony 6.4 or higher
- Doctrine ORM 3.0 or higher
- Doctrine DBAL 4.0 or higher

## Quick Start

```php
<?php

use AsyncExportBundle\Entity\AsyncExportTask;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Security;

// Create a new export task
$task = new AsyncExportTask();
$task->setDql('SELECT u FROM App\Entity\User u WHERE u.active = true');
$task->setEntityClass('App\Entity\User');
$task->setColumns(['id', 'email', 'name', 'createTime']);
$task->setFile('users_export.csv');
$task->setRemark('Export active users');
$task->setUser($security->getUser()); // Associate with current user
$task->setValid(true);

// Save the task
$entityManager->persist($task);
$entityManager->flush();

// The task is now ready for background processing
// Use Symfony Messenger or similar for async execution
```

## Configuration

### Bundle Registration

Add the bundle to your `config/bundles.php`:

```php
<?php

return [
    // ...
    AsyncExportBundle\AsyncExportBundle::class => ['all' => true],
];
```

### Service Configuration

The bundle automatically configures services in `services.yaml`:

```yaml
# config/services.yaml or in your bundle
services:
    AsyncExportBundle\Repository\:
        resource: '../src/Repository/'
```

### Entity Mapping

Ensure Doctrine is configured to scan the bundle entities:

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

## Entity Structure

The `AsyncExportTask` entity provides comprehensive export task management:

### Core Properties

- **`user`**: Associated user (UserInterface)
- **`file`**: Export file path (string, max 1000 chars)
- **`entityClass`**: Target entity class name for EntityManager lookup
- **`dql`**: Doctrine Query Language string for data selection
- **`columns`**: JSON array defining export column configuration

### Tracking Properties

- **`totalCount`**: Total number of records to export
- **`processCount`**: Number of records already processed
- **`memoryUsage`**: Memory consumption during processing
- **`valid`**: Task validation status flag

### Metadata Properties

- **`remark`**: Human-readable task description
- **`exception`**: Error message storage for failed tasks
- **`json`**: Additional parameter storage

### Inherited Traits

- **SnowflakeKeyAware**: Unique ID generation
- **TimestampableAware**: Created/updated timestamps
- **BlameableAware**: User tracking for audit trails

## Repository Usage

```php
<?php

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use Doctrine\ORM\EntityManagerInterface;

// Get repository
$repository = $entityManager->getRepository(AsyncExportTask::class);

// Find tasks by user
$userTasks = $repository->findBy(['user' => $user]);

// Find valid/pending tasks
$validTasks = $repository->findBy(['valid' => true]);

// Find tasks by entity class
$entityTasks = $repository->findBy(['entityClass' => 'App\Entity\User']);

// Find tasks with progress tracking
$incompleteTasks = $repository->createQueryBuilder('t')
    ->where('t.processCount < t.totalCount')
    ->andWhere('t.valid = true')
    ->getQuery()
    ->getResult();
```

## Advanced Usage

### Progress Tracking

```php
// Update task progress during processing
$task->setTotalCount(1000);
$task->setProcessCount(250); // 25% complete
$task->setMemoryUsage(memory_get_usage());
$entityManager->flush();
```

### Error Handling

```php
try {
    // Process export
    processExport($task);
} catch (\Exception $e) {
    $task->setException($e->getMessage());
    $task->setValid(false);
    $entityManager->flush();
}
```

## Security

### Access Control

The bundle integrates with Symfony's security component to ensure proper access
control:

- All export tasks are associated with authenticated users
- Users can only access their own export tasks
- Task creation requires valid authentication
- File paths should be validated to prevent directory traversal

### Data Protection

- Export files should be stored in secure locations
- Sensitive data in DQL queries should be properly escaped
- Regular cleanup of completed export files is recommended
- Memory usage tracking helps prevent resource exhaustion

### Best Practices

- Always validate user input for DQL queries
- Implement proper file permission controls
- Use secure file naming conventions
- Monitor export task execution for anomalies
- Implement rate limiting for export task creation

## Console Commands

The bundle provides the following console commands for managing export tasks:

### `async-export:process`

Process a single export task by ID.

**Usage:**
```bash
php bin/console async-export:process <task-id> [--force]
```

**Arguments:**
- `task-id`: The ID of the export task to process (required)

**Options:**
- `--force`: Force process the task even if it's already been processed

**Examples:**
```bash
# Process task with ID 123
php bin/console async-export:process 123

# Force reprocess task with ID 456
php bin/console async-export:process 456 --force
```

### `async-export:process-all`

Process all pending export tasks in the queue.

**Usage:**
```bash
php bin/console async-export:process-all [--limit=LIMIT] [--force]
```

**Options:**
- `--limit=LIMIT`: Maximum number of tasks to process (default: 10)
- `--force`: Force process tasks even if they've already been processed

**Examples:**
```bash
# Process up to 10 pending tasks (default)
php bin/console async-export:process-all

# Process up to 50 tasks
php bin/console async-export:process-all --limit=50

# Force reprocess all pending tasks
php bin/console async-export:process-all --force

# Process unlimited tasks (use with caution)
php bin/console async-export:process-all --limit=0
```

**Note:** These commands are typically run by background job processors or cron jobs to handle export tasks asynchronously.

## Contributing

Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.