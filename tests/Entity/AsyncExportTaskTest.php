<?php

namespace AsyncExportBundle\Tests\Entity;

use AsyncExportBundle\Entity\AsyncExportTask;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class AsyncExportTaskTest extends TestCase
{
    private AsyncExportTask $task;

    protected function setUp(): void
    {
        $this->task = new AsyncExportTask();
    }

    public function testInitialValues(): void
    {
        $this->assertNull($this->task->getId());
        $this->assertNull($this->task->getUser());
        $this->assertNull($this->task->getFile());
        $this->assertNull($this->task->getEntityClass());
        $this->assertNull($this->task->getDql());
        $this->assertEquals([], $this->task->getColumns());
        $this->assertNull($this->task->getRemark());
        $this->assertNull($this->task->getException());
        $this->assertEquals([], $this->task->getJson());
        $this->assertEquals(0, $this->task->getTotalCount());
        $this->assertEquals(0, $this->task->getProcessCount());
        $this->assertEquals(0, $this->task->getMemoryUsage());
        $this->assertFalse($this->task->isValid());
        $this->assertNull($this->task->getCreatedBy());
        $this->assertNull($this->task->getUpdatedBy());
        $this->assertNull($this->task->getCreateTime());
        $this->assertNull($this->task->getUpdateTime());
    }

    public function testGetAndSetId(): void
    {
        $this->assertNull($this->task->getId(), '新建实体的ID应为null');
        
        // 注意：实际应用中ID通常由Doctrine自动生成，这里仅测试getter
    }

    public function testGetAndSetUser(): void
    {
        $user = $this->createMock(UserInterface::class);
        
        $result = $this->task->setUser($user);
        
        $this->assertSame($this->task, $result, 'setUser应当返回实体本身以支持链式调用');
        $this->assertSame($user, $this->task->getUser(), 'getUser应当返回之前设置的用户');
        
        // 测试设置null值
        $result = $this->task->setUser(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->getUser(), 'getUser在设置null后应返回null');
    }

    public function testGetAndSetFile(): void
    {
        $file = 'test_file.csv';
        
        $result = $this->task->setFile($file);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($file, $this->task->getFile());
    }

    public function testGetAndSetEntityClass(): void
    {
        $entityClass = 'App\Entity\TestEntity';
        
        $result = $this->task->setEntityClass($entityClass);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($entityClass, $this->task->getEntityClass());
        
        // 测试设置null值
        $result = $this->task->setEntityClass(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->getEntityClass());
    }

    public function testGetAndSetDql(): void
    {
        $dql = 'SELECT e FROM Entity e WHERE e.id = :id';
        
        $result = $this->task->setDql($dql);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($dql, $this->task->getDql());
    }

    public function testGetAndSetColumns(): void
    {
        $columns = [
            'id' => ['label' => 'ID', 'width' => 10],
            'name' => ['label' => '名称', 'width' => 20],
        ];
        
        $result = $this->task->setColumns($columns);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($columns, $this->task->getColumns());
    }

    public function testGetAndSetRemark(): void
    {
        $remark = 'Test remark';
        
        $result = $this->task->setRemark($remark);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($remark, $this->task->getRemark());
        
        // 测试设置null值
        $result = $this->task->setRemark(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->getRemark());
    }

    public function testGetAndSetException(): void
    {
        $exception = 'Test exception message';
        
        $result = $this->task->setException($exception);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($exception, $this->task->getException());
        
        // 测试设置null值
        $result = $this->task->setException(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->getException());
    }

    public function testGetAndSetJson(): void
    {
        $json = ['param1' => 'value1', 'param2' => 'value2'];
        
        $result = $this->task->setJson($json);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($json, $this->task->getJson());
        
        // 测试设置null值
        $result = $this->task->setJson(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->getJson());
    }

    public function testGetAndSetTotalCount(): void
    {
        $totalCount = 100;
        
        $result = $this->task->setTotalCount($totalCount);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($totalCount, $this->task->getTotalCount());
        
        // 测试设置null值
        $result = $this->task->setTotalCount(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->getTotalCount());
    }

    public function testGetAndSetProcessCount(): void
    {
        $processCount = 50;
        
        $result = $this->task->setProcessCount($processCount);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($processCount, $this->task->getProcessCount());
        
        // 测试设置null值
        $result = $this->task->setProcessCount(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->getProcessCount());
    }

    public function testGetAndSetMemoryUsage(): void
    {
        $memoryUsage = 1024;
        
        $result = $this->task->setMemoryUsage($memoryUsage);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($memoryUsage, $this->task->getMemoryUsage());
        
        // 测试设置null值
        $result = $this->task->setMemoryUsage(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->getMemoryUsage());
    }

    public function testGetAndSetValid(): void
    {
        $valid = true;
        
        $result = $this->task->setValid($valid);
        
        $this->assertSame($this->task, $result);
        $this->assertTrue($this->task->isValid());
        
        // 测试设置false值
        $result = $this->task->setValid(false);
        
        $this->assertSame($this->task, $result);
        $this->assertFalse($this->task->isValid());
        
        // 测试设置null值
        $result = $this->task->setValid(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->isValid());
    }

    public function testGetAndSetCreatedBy(): void
    {
        $createdBy = 'user1';
        
        $result = $this->task->setCreatedBy($createdBy);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($createdBy, $this->task->getCreatedBy());
        
        // 测试设置null值
        $result = $this->task->setCreatedBy(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->getCreatedBy());
    }

    public function testGetAndSetUpdatedBy(): void
    {
        $updatedBy = 'user2';
        
        $result = $this->task->setUpdatedBy($updatedBy);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($updatedBy, $this->task->getUpdatedBy());
        
        // 测试设置null值
        $result = $this->task->setUpdatedBy(null);
        
        $this->assertSame($this->task, $result);
        $this->assertNull($this->task->getUpdatedBy());
    }

    public function testGetAndSetCreateTime(): void
    {
        $createTime = new \DateTimeImmutable('2023-01-01 12:00:00');
        
        $this->task->setCreateTime($createTime);
        
        $this->assertEquals($createTime, $this->task->getCreateTime());
        
        // 测试设置null值
        $this->task->setCreateTime(null);
        
        $this->assertNull($this->task->getCreateTime());
    }

    public function testGetAndSetUpdateTime(): void
    {
        $updateTime = new \DateTimeImmutable('2023-01-02 12:00:00');
        
        $this->task->setUpdateTime($updateTime);
        
        $this->assertEquals($updateTime, $this->task->getUpdateTime());
        
        // 测试设置null值
        $this->task->setUpdateTime(null);
        
        $this->assertNull($this->task->getUpdateTime());
    }

    public function testMethodChaining(): void
    {
        $file = 'test.csv';
        $entityClass = 'App\Entity\Test';
        $dql = 'SELECT e FROM Entity e';
        
        $result = $this->task
            ->setFile($file)
            ->setEntityClass($entityClass)
            ->setDql($dql);
        
        $this->assertSame($this->task, $result);
        $this->assertEquals($file, $this->task->getFile());
        $this->assertEquals($entityClass, $this->task->getEntityClass());
        $this->assertEquals($dql, $this->task->getDql());
    }
} 