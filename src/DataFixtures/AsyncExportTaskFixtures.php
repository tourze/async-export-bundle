<?php

declare(strict_types=1);

namespace AsyncExportBundle\DataFixtures;

use AsyncExportBundle\Entity\AsyncExportTask;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AsyncExportTaskFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $task1 = new AsyncExportTask();
        $task1->setDql('SELECT u FROM App\Entity\User u');
        $task1->setEntityClass('App\Entity\User');
        $task1->setFile('users_export.xlsx');
        $task1->setColumns([
            ['field' => 'id', 'label' => 'ID', 'type' => 'string'],
            ['field' => 'name', 'label' => '姓名', 'type' => 'string'],
            ['field' => 'email', 'label' => '邮箱', 'type' => 'string'],
        ]);
        $task1->setTotalCount(100);
        $task1->setProcessCount(0);
        $task1->setMemoryUsage(0);
        $task1->setValid(true);
        $task1->setRemark('用户数据导出任务');

        $manager->persist($task1);

        $task2 = new AsyncExportTask();
        $task2->setDql('SELECT o FROM App\Entity\Order o WHERE o.status = :status');
        $task2->setEntityClass('App\Entity\Order');
        $task2->setFile('orders_export.xlsx');
        $task2->setColumns([
            ['field' => 'id', 'label' => 'ID', 'type' => 'string'],
            ['field' => 'orderNumber', 'label' => '订单号', 'type' => 'string'],
            ['field' => 'amount', 'label' => '金额', 'type' => 'number'],
            ['field' => 'status', 'label' => '状态', 'type' => 'string'],
        ]);
        $task2->setJson(['status' => 'completed']);
        $task2->setTotalCount(500);
        $task2->setProcessCount(250);
        $task2->setMemoryUsage(1024000);
        $task2->setValid(true);
        $task2->setRemark('已完成订单导出任务');

        $manager->persist($task2);

        $task3 = new AsyncExportTask();
        $task3->setDql('SELECT p FROM App\Entity\Product p');
        $task3->setEntityClass('App\Entity\Product');
        $task3->setFile('products_export.xlsx');
        $task3->setColumns([
            ['field' => 'id', 'label' => 'ID', 'type' => 'string'],
            ['field' => 'name', 'label' => '名称', 'type' => 'string'],
            ['field' => 'price', 'label' => '价格', 'type' => 'number'],
            ['field' => 'category', 'label' => '分类', 'type' => 'string'],
        ]);
        $task3->setTotalCount(1000);
        $task3->setProcessCount(1000);
        $task3->setMemoryUsage(2048000);
        $task3->setValid(false);
        $task3->setException('内存不足，导出失败');
        $task3->setRemark('产品数据导出任务 - 已失败');

        $manager->persist($task3);

        $manager->flush();
    }
}
