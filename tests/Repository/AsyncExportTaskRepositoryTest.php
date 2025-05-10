<?php

namespace AsyncExportBundle\Tests\Repository;

use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class AsyncExportTaskRepositoryTest extends TestCase
{
    private $registry;
    private $repository;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->repository = new AsyncExportTaskRepository($this->registry);
    }

    public function testConstruction(): void
    {
        $this->assertInstanceOf(AsyncExportTaskRepository::class, $this->repository);
    }

    public function testEntityClass(): void
    {
        // 检查是否在构造函数中使用了正确的实体类
        $repositoryReflection = new \ReflectionClass(AsyncExportTaskRepository::class);
        $constructorBody = file_get_contents($repositoryReflection->getFileName());
        
        $this->assertStringContainsString(
            'parent::__construct($registry, AsyncExportTask::class)',
            $constructorBody,
            '仓库构造函数应该将正确的实体类传递给父类构造函数'
        );
    }
} 