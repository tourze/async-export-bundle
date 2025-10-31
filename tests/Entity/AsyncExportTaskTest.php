<?php

namespace AsyncExportBundle\Tests\Entity;

use AsyncExportBundle\Entity\AsyncExportTask;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncExportTask::class)]
final class AsyncExportTaskTest extends AbstractEntityTestCase
{
    protected function createEntity(): AsyncExportTask
    {
        // 通过反射或者从容器获取实例，避免直接实例化
        $class = AsyncExportTask::class;
        $reflection = new \ReflectionClass($class);

        return $reflection->newInstance();
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    /**
     * @return \Generator<string, array{string, mixed}>
     */
    public static function propertiesProvider(): \Generator
    {
        // 为每个属性提供测试样本值
        yield 'user' => ['user', null];
        yield 'file' => ['file', 'test_file.csv'];
        yield 'entityClass' => ['entityClass', 'App\Entity\TestEntity'];
        yield 'dql' => ['dql', 'SELECT e FROM Entity e WHERE e.id = :id'];
        yield 'columns' => ['columns', ['id' => ['label' => 'ID', 'width' => 10]]];
        yield 'remark' => ['remark', 'Test remark'];
        yield 'exception' => ['exception', 'Test exception message'];
        yield 'json' => ['json', ['param1' => 'value1', 'param2' => 'value2']];
        yield 'totalCount' => ['totalCount', 100];
        yield 'processCount' => ['processCount', 50];
        yield 'memoryUsage' => ['memoryUsage', 1024];
        yield 'valid' => ['valid', true];
        yield 'createdBy' => ['createdBy', 'user1'];
        yield 'updatedBy' => ['updatedBy', 'user2'];
        yield 'createTime' => ['createTime', new \DateTimeImmutable('2023-01-01 12:00:00')];
        yield 'updateTime' => ['updateTime', new \DateTimeImmutable('2023-01-02 12:00:00')];
    }

    public function testInitialValues(): void
    {
        $task = $this->createEntity();

        $this->assertNull($task->getId());
        $this->assertNull($task->getUser());
        $this->assertNull($task->getFile());
        $this->assertNull($task->getEntityClass());
        $this->assertNull($task->getDql());
        $this->assertEquals([], $task->getColumns());
        $this->assertNull($task->getRemark());
        $this->assertNull($task->getException());
        $this->assertEquals([], $task->getJson());
        $this->assertEquals(0, $task->getTotalCount());
        $this->assertEquals(0, $task->getProcessCount());
        $this->assertEquals(0, $task->getMemoryUsage());
        $this->assertFalse($task->isValid());
        $this->assertNull($task->getCreatedBy());
        $this->assertNull($task->getUpdatedBy());
        $this->assertNull($task->getCreateTime());
        $this->assertNull($task->getUpdateTime());
    }

    public function testGetAndSetUser(): void
    {
        $task = $this->createEntity();
        $user = $this->createMock(UserInterface::class);

        $task->setUser($user);

        $this->assertSame($user, $task->getUser(), 'getUser应当返回之前设置的用户');

        // 测试设置null值
        $task->setUser(null);
        $this->assertNull($task->getUser(), 'getUser在设置null后应返回null');
    }

    public function testMethodChaining(): void
    {
        $task = $this->createEntity();
        $file = 'test.csv';
        $entityClass = 'App\Entity\Test';
        $dql = 'SELECT e FROM Entity e';

        $task->setFile($file);
        $task->setEntityClass($entityClass);
        $task->setDql($dql);
        $this->assertEquals($file, $task->getFile());
        $this->assertEquals($entityClass, $task->getEntityClass());
        $this->assertEquals($dql, $task->getDql());
    }
}
