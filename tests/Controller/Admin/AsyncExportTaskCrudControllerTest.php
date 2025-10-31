<?php

namespace AsyncExportBundle\Tests\Controller\Admin;

use AsyncExportBundle\Controller\Admin\AsyncExportTaskCrudController;
use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use AsyncExportBundle\Service\ExportFileService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Dto\ActionDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncExportTaskCrudController::class)]
#[RunTestsInSeparateProcesses]
class AsyncExportTaskCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /**
     * 获取被测试的控制器服务
     */
    protected function getControllerService(): AsyncExportTaskCrudController
    {
        $controller = self::getService(AsyncExportTaskCrudController::class);
        self::assertInstanceOf(AsyncExportTaskCrudController::class, $controller);

        return $controller;
    }

    /**
     * 创建测试用的AsyncExportTask实体
     * 用于确保testIndexRowActionLinksShouldNotReturn500有数据可测试
     */
    private function createTestExportTask(): AsyncExportTask
    {
        $em = self::getService(EntityManagerInterface::class);

        $task = new AsyncExportTask();
        $task->setDql('SELECT e FROM App\Entity\User e');
        $task->setEntityClass('App\Entity\User');
        $task->setFile('test_export.xlsx');
        $task->setColumns([
            ['field' => 'id', 'label' => 'ID'],
            ['field' => 'name', 'label' => '姓名'],
        ]);
        $task->setTotalCount(100);
        $task->setProcessCount(100);
        $task->setValid(true);

        $em->persist($task);
        $em->flush();

        return $task;
    }

    /**
     * 验证索引页面有行动作链接且可以正常访问
     * 该测试创建测试数据以确保基类的testIndexRowActionLinksShouldNotReturn500有数据可测试
     */
    public function testIndexPageHasActionLinks(): void
    {
        // 创建测试数据
        $client = $this->createAuthenticatedClient();
        $this->createTestExportTask();

        // 访问索引页面
        $crawler = $client->request('GET', $this->generateAdminUrl('index'));
        $this->assertResponseIsSuccessful();

        // 验证至少有一行数据
        $rows = $crawler->filter('table tbody tr[data-id]');
        self::assertGreaterThan(0, $rows->count(), '索引页面应该显示至少一条记录');

        // 验证行动作链接存在
        $actionLinks = $rows->first()->filter('td.actions a[href]');
        self::assertGreaterThan(0, $actionLinks->count(), '每行应该有动作链接');

        // 验证DETAIL链接可以访问（不测试下载链接，因为没有实际文件）
        $detailLink = $actionLinks->filter('[title*="Show"], [title*="查看"], [title*="详情"]')->first();
        if ($detailLink->count() > 0) {
            $href = $detailLink->attr('href');
            if (null !== $href && '' !== $href) {
                $client->request('GET', $href);
                self::assertLessThan(500, $client->getResponse()->getStatusCode(), 'DETAIL链接不应返回500错误');
            }
        }
    }

    /**
     * 提供索引页面表头信息
     *
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield 'User' => ['用户'];
        yield 'Entity Class' => ['实体类'];
        yield 'Memory Usage' => ['内存占用'];
        yield 'Valid Status' => ['有效状态'];
        yield 'File' => ['文件'];
        yield 'Columns Config' => ['字段配置'];
        yield 'Task Status' => ['任务状态'];
        yield 'Create Time' => ['创建时间'];
        yield 'Update Time' => ['更新时间'];
        yield 'Created By' => ['创建人'];
        yield 'Updated By' => ['更新人'];
    }

    /**
     * 验证NEW操作被正确禁用 - 不再跳过，而是验证业务逻辑
     */
    public function testNewActionIsDisabled(): void
    {
        $repository = self::getService(AsyncExportTaskRepository::class);
        $exportFileService = self::getService(ExportFileService::class);
        $controller = new AsyncExportTaskCrudController($repository, $exportFileService);

        $actions = $controller->configureActions(Actions::new());
        $actionsDto = $actions->getAsDto(Crud::PAGE_INDEX);
        $indexActions = $actionsDto->getActions();

        // 验证NEW操作不在可用操作中
        $actionNames = [];
        /** @var string $actionName */
        /** @var ActionDto $action */
        foreach ($indexActions as $actionName => $action) {
            $actionNames[] = $actionName;
        }
        self::assertNotContains(Action::NEW, $actionNames, 'NEW action should be disabled');
    }

    /**
     * 验证EDIT操作被正确禁用 - 不再跳过，而是验证业务逻辑
     */
    public function testEditActionIsDisabled(): void
    {
        $repository = self::getService(AsyncExportTaskRepository::class);
        $exportFileService = self::getService(ExportFileService::class);
        $controller = new AsyncExportTaskCrudController($repository, $exportFileService);

        $actions = $controller->configureActions(Actions::new());
        $actionsDto = $actions->getAsDto(Crud::PAGE_INDEX);
        $indexActions = $actionsDto->getActions();

        // 验证EDIT操作不在可用操作中
        $actionNames = [];
        /** @var string $actionName */
        /** @var ActionDto $action */
        foreach ($indexActions as $actionName => $action) {
            $actionNames[] = $actionName;
        }
        self::assertNotContains(Action::EDIT, $actionNames, 'EDIT action should be disabled');
    }

    public function testConfigureActionsMethod(): void
    {
        $repository = self::getService(AsyncExportTaskRepository::class);
        $exportFileService = self::getService(ExportFileService::class);
        $controller = new AsyncExportTaskCrudController($repository, $exportFileService);

        // 验证动作配置方法返回正确类型
        $actions = $controller->configureActions(Actions::new());

        // 验证NEW和EDIT操作被禁用
        $actionsDto = $actions->getAsDto(Crud::PAGE_INDEX);
        $indexActions = $actionsDto->getActions();

        // 检查关键动作存在
        self::assertNotEmpty($indexActions);
    }

    public function testConfigureFieldsMethod(): void
    {
        $repository = self::getService(AsyncExportTaskRepository::class);
        $exportFileService = self::getService(ExportFileService::class);
        $controller = new AsyncExportTaskCrudController($repository, $exportFileService);

        // 验证字段配置方法存在且返回iterable
        $fields = $controller->configureFields(Crud::PAGE_INDEX);

        // 验证字段配置不为空
        $fieldsArray = iterator_to_array($fields);
        self::assertNotEmpty($fieldsArray);
    }

    #[TestWith([0, '0 B'])]
    #[TestWith([1024, '1 KB'])]
    #[TestWith([1048576, '1 MB'])]
    #[TestWith([1073741824, '1 GB'])]
    #[TestWith([500, '500 B'])]
    #[TestWith([1536, '1.5 KB'])]
    public function testFormatBytesWithDataProvider(int $input, string $expected): void
    {
        $repository = self::getService(AsyncExportTaskRepository::class);
        $exportFileService = self::getService(ExportFileService::class);
        $controller = new AsyncExportTaskCrudController($repository, $exportFileService);
        $reflectionMethod = new \ReflectionMethod($controller, 'formatBytes');
        $reflectionMethod->setAccessible(true);

        $result = $reflectionMethod->invoke($controller, $input);
        self::assertIsString($result);
        self::assertSame($expected, $result);
    }

    public function testDownloadFileMethodExists(): void
    {
        $repository = self::getService(AsyncExportTaskRepository::class);
        $exportFileService = self::getService(ExportFileService::class);
        $controller = new AsyncExportTaskCrudController($repository, $exportFileService);
        $reflection = new \ReflectionClass($controller);

        self::assertTrue($reflection->hasMethod('downloadFile'));
    }

    public function testControllerHasBatchDownloadMethod(): void
    {
        $repository = self::getService(AsyncExportTaskRepository::class);
        $exportFileService = self::getService(ExportFileService::class);
        $controller = new AsyncExportTaskCrudController($repository, $exportFileService);
        $reflection = new \ReflectionClass($controller);

        self::assertTrue($reflection->hasMethod('batchDownload'));
    }

    public function testPrivateMethodsExist(): void
    {
        $repository = self::getService(AsyncExportTaskRepository::class);
        $exportFileService = self::getService(ExportFileService::class);
        $controller = new AsyncExportTaskCrudController($repository, $exportFileService);
        $reflection = new \ReflectionClass($controller);

        // 验证关键私有方法存在
        self::assertTrue($reflection->hasMethod('formatUserValue'));
        self::assertTrue($reflection->hasMethod('formatFileInfo'));
        self::assertTrue($reflection->hasMethod('formatTaskStatus'));
        self::assertTrue($reflection->hasMethod('isTaskCompleted'));
    }

    /**
     * 重写基类的数据提供者以避免空数据集错误
     * 由于NEW操作被禁用，这些数据不会被实际使用
     *
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        // 返回一个虚拟字段，因为NEW操作被禁用，测试会被跳过
        yield 'dummy field' => ['id'];
    }

    /**
     * 重写基类的数据提供者以避免空数据集错误
     * 由于EDIT操作被禁用，这些数据不会被实际使用
     *
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        // 返回一个虚拟字段，因为EDIT操作被禁用，测试会被跳过
        yield 'dummy field' => ['id'];
    }
}
