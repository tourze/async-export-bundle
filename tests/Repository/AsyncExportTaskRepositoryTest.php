<?php

namespace AsyncExportBundle\Tests\Repository;

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @template-extends AbstractRepositoryTestCase<AsyncExportTask>
 * @internal
 */
#[CoversClass(AsyncExportTaskRepository::class)]
#[RunTestsInSeparateProcesses]
final class AsyncExportTaskRepositoryTest extends AbstractRepositoryTestCase
{
    protected function onSetUp(): void
    {
        // 没有特殊设置需求
    }

    protected function createNewEntity(): AsyncExportTask
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e WHERE e.id = ' . uniqid());
        $entity->setEntityClass('TestEntity');
        $entity->setFile('test-file-' . uniqid() . '.csv');
        $entity->setColumns([
            ['field' => 'id', 'label' => 'ID'],
            ['field' => 'name', 'label' => 'Name'],
            ['field' => 'email', 'label' => 'Email'],
        ]);
        $entity->setRemark('Test remark ' . uniqid());
        $entity->setTotalCount(100);
        $entity->setProcessCount(50);
        $entity->setMemoryUsage(1024);
        $entity->setValid(true);
        $entity->setJson(['test' => 'data']);

        return $entity;
    }

    protected function getRepository(): ServiceEntityRepository
    {
        return self::getService(AsyncExportTaskRepository::class);
    }

    public function testFindByWithMultipleConditionsShouldReturnCorrectResults(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');
        $entity->setValid(true);
        $entity->setEntityClass('TestEntity');

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        $repository = self::getService(AsyncExportTaskRepository::class);
        $results = $repository->findBy(['valid' => true, 'entityClass' => 'TestEntity']);

        self::assertIsArray($results);
        self::assertGreaterThanOrEqual(1, count($results));
        foreach ($results as $result) {
            self::assertInstanceOf(AsyncExportTask::class, $result);
            self::assertTrue($result->isValid());
            self::assertEquals('TestEntity', $result->getEntityClass());
        }
    }

    public function testCountWithMultipleCriteriaShouldReturnCorrectNumber(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');
        $entity->setValid(true);
        $entity->setEntityClass('TestEntity');

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        $repository = self::getService(AsyncExportTaskRepository::class);
        $count = $repository->count(['valid' => true, 'entityClass' => 'TestEntity']);

        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(1, $count);
    }

    public function testSaveEntityShouldPersistToDatabase(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');

        $repository = self::getService(AsyncExportTaskRepository::class);
        $repository->save($entity);

        self::assertNotNull($entity->getId());

        // 验证实体已保存到数据库
        $savedEntity = $repository->find($entity->getId());
        self::assertInstanceOf(AsyncExportTask::class, $savedEntity);
        self::assertEquals('SELECT e FROM TestEntity e', $savedEntity->getDql());
    }

    public function testSaveEntityWithFlushFalseShouldNotFlush(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e WHERE id = ' . uniqid());

        $repository = self::getService(AsyncExportTaskRepository::class);
        $repository->save($entity, false);

        // 使用 Snowflake ID，persist 时就会分配 ID，但数据不会立即保存到数据库
        self::assertNotNull($entity->getId());

        // 创建一个新的EntityManager来测试是否真的没有flush
        $entityManager = self::getService(EntityManagerInterface::class);
        $uow = $entityManager->getUnitOfWork();

        // 检查实体是否在工作单元中被标记为待插入
        self::assertTrue($uow->isScheduledForInsert($entity), '实体应该被安排插入但尚未flush');

        // 或者通过detach然后查找来验证
        $entityId = $entity->getId();
        $entityManager->detach($entity); // 从EntityManager中分离
        $foundEntity = $repository->find($entityId);
        self::assertNull($foundEntity, '实体不应该在数据库中找到，因为没有flush');
    }

    public function testRemoveEntityShouldDeleteFromDatabase(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        $entityId = $entity->getId();
        self::assertNotNull($entityId);

        $repository = self::getService(AsyncExportTaskRepository::class);
        $repository->remove($entity);

        // 验证实体已从数据库中删除
        $deletedEntity = $repository->find($entityId);
        self::assertNull($deletedEntity);
    }

    public function testRemoveEntityWithFlushFalseShouldNotFlush(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        $entityId = $entity->getId();
        $repository = self::getService(AsyncExportTaskRepository::class);
        $repository->remove($entity, false);

        // 实体应该仍然存在（因为没有 flush）
        $stillExistsEntity = $repository->find($entityId);
        self::assertInstanceOf(AsyncExportTask::class, $stillExistsEntity);
    }

    // 测试可空字段的 IS NULL 查询
    public function testFindByWithNullableFieldIsNull(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');
        // file 字段默认为 null，无需设置

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        $repository = self::getService(AsyncExportTaskRepository::class);
        $results = $repository->findBy(['file' => null]);

        self::assertIsArray($results);
        self::assertGreaterThanOrEqual(1, count($results));
    }

    public function testCountWithNullableFieldIsNull(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');
        $entity->setEntityClass(null); // 设置为 null

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        $repository = self::getService(AsyncExportTaskRepository::class);
        $count = $repository->count(['entityClass' => null]);

        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByWithNullableRemark(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');
        $entity->setRemark(null);

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        $repository = self::getService(AsyncExportTaskRepository::class);
        $results = $repository->findBy(['remark' => null]);

        self::assertIsArray($results);
        self::assertGreaterThanOrEqual(1, count($results));
    }

    public function testCountWithNullableException(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');
        $entity->setException(null);

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        $repository = self::getService(AsyncExportTaskRepository::class);
        $count = $repository->count(['exception' => null]);

        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(1, $count);
    }

    public function testCountByAssociationUserShouldReturnCorrectNumber(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');
        $entity->setUser(null);

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        $repository = self::getService(AsyncExportTaskRepository::class);
        $count = $repository->count(['user' => null]);

        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(1, $count);
    }

    /**
     * 针对 Snowflake ID 的专用测试：验证 save(entity, false) 时不会立即持久化到数据库
     * 由于父类的 testSaveWithFlushFalseShouldNotImmediatelyPersist 被跳过，这里提供专门的测试
     */
    public function testSaveWithFlushFalseForSnowflakeIdShouldNotImmediatelyPersist(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e WHERE id = ' . uniqid());

        $repository = self::getService(AsyncExportTaskRepository::class);
        $repository->save($entity, false);

        // 使用 Snowflake ID，persist 时就会分配 ID，但数据不会立即保存到数据库
        self::assertNotNull($entity->getId());

        // 创建一个新的EntityManager来测试是否真的没有flush
        $entityManager = self::getService(EntityManagerInterface::class);
        $uow = $entityManager->getUnitOfWork();

        // 检查实体是否在工作单元中被标记为待插入
        self::assertTrue($uow->isScheduledForInsert($entity), '实体应该被安排插入但尚未flush');

        // 通过detach然后查找来验证数据库中确实没有这条记录
        $entityId = $entity->getId();
        $entityManager->detach($entity); // 从EntityManager中分离
        $foundEntity = $repository->find($entityId);
        self::assertNull($foundEntity, '实体不应该在数据库中找到，因为没有flush');
    }

    public function testFindOneByAssociationUserShouldReturnMatchingEntity(): void
    {
        $entity = new AsyncExportTask();
        $entity->setDql('SELECT e FROM TestEntity e');
        $entity->setUser(null);

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($entity);
        $entityManager->flush();

        $repository = self::getService(AsyncExportTaskRepository::class);
        $result = $repository->findOneBy(['user' => null]);

        self::assertInstanceOf(AsyncExportTask::class, $result);
        self::assertNull($result->getUser());
    }

    public function testFindPendingTasksShouldReturnPendingTasks(): void
    {
        // 创建待处理任务
        $pendingTask = new AsyncExportTask();
        $pendingTask->setDql('SELECT e FROM TestEntity e');
        $pendingTask->setValid(false); // 待处理状态
        $pendingTask->setTotalCount(100);
        $pendingTask->setProcessCount(50); // 未完成

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($pendingTask);
        $entityManager->flush();

        $repository = self::getService(AsyncExportTaskRepository::class);
        $pendingTasks = $repository->findPendingTasks(10);

        self::assertIsArray($pendingTasks);
        self::assertGreaterThanOrEqual(1, count($pendingTasks));
        foreach ($pendingTasks as $task) {
            self::assertInstanceOf(AsyncExportTask::class, $task);
        }
    }

    public function testFindCompletedTasksShouldReturnCompletedTasks(): void
    {
        // 创建已完成任务
        $completedTask = new AsyncExportTask();
        $completedTask->setDql('SELECT e FROM TestEntity e');
        $completedTask->setValid(true); // 有效状态
        $completedTask->setTotalCount(100);
        $completedTask->setProcessCount(100); // 已完成

        $entityManager = self::getService(EntityManagerInterface::class);
        $entityManager->persist($completedTask);
        $entityManager->flush();

        $repository = self::getService(AsyncExportTaskRepository::class);
        $completedTasks = $repository->findCompletedTasks(100);

        self::assertIsArray($completedTasks);
        self::assertGreaterThanOrEqual(1, count($completedTasks));
        foreach ($completedTasks as $task) {
            self::assertInstanceOf(AsyncExportTask::class, $task);
            self::assertTrue($task->isValid());
            self::assertGreaterThanOrEqual($task->getTotalCount(), $task->getProcessCount());
        }
    }
}
