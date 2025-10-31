<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Trait;

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Trait\AsyncExportableTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\EasyAdminImagePreviewFieldBundle\Field\ImagePreviewField;

/**
 * @internal
 */
#[CoversClass(AsyncExportableTrait::class)]
final class AsyncExportableTraitTest extends TestCase
{
    private TestCrudController $controller;

    /** @var MockObject&EntityManagerInterface */
    private EntityManagerInterface $entityManager;

    /** @var MockObject&UserInterface */
    private UserInterface $user;

    /** @var MockObject&AdminContextInterface */
    private AdminContextInterface $adminContext;

    private Session $session;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->user = $this->createMock(UserInterface::class);
        $this->user->method('getUserIdentifier')->willReturn('test_user');

        $this->session = new Session(new MockArraySessionStorage());
        $request = Request::create('/test');
        $request->setSession($this->session);

        $searchDto = new SearchDto($request, ['name'], '', [], [], [], 'all_terms');
        $this->adminContext = $this->createMockAdminContext($request, $searchDto);

        $this->controller = new TestCrudController();
        $this->controller->setEntityManager($this->entityManager);
        $this->controller->setUser($this->user);
    }

    public function testConfigureAsyncExportActions(): void
    {
        $result = $this->controller->configureAsyncExportActions();

        $this->assertInstanceOf(Action::class, $result);
    }

    public function testTriggerAsyncExportWithoutUser(): void
    {
        $this->controller->setUser(null);

        $response = $this->controller->triggerAsyncExport($this->adminContext);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($this->controller->hasFlashMessage('danger', '请先登录'));
    }

    public function testTriggerAsyncExportSuccess(): void
    {
        $this->setupEntityManagerForSuccess();

        $response = $this->controller->triggerAsyncExport($this->adminContext);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($this->controller->hasFlashMessage('success'));
    }

    public function testTriggerAsyncExportWithException(): void
    {
        $this->setupEntityManagerForException();

        $response = $this->controller->triggerAsyncExport($this->adminContext);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertTrue($this->controller->hasFlashMessage('danger'));
    }

    public function testCreateAsyncExportTask(): void
    {
        $this->setupEntityManagerForSuccess();

        $task = $this->controller->createAsyncExportTaskPublic($this->user, $this->adminContext);

        $this->assertInstanceOf(AsyncExportTask::class, $task);
        $this->assertEquals('AsyncExportBundle\Tests\Trait\TestEntity', $task->getEntityClass());
        $this->assertSame($this->user, $task->getUser());
        $this->assertNotEmpty($task->getDql());
        $this->assertNotEmpty($task->getColumns());
        $this->assertNotEmpty($task->getFile());
        $this->assertNotEmpty($task->getRemark());
    }

    public function testCreateExportQueryBuilder(): void
    {
        $this->setupEntityManagerForSuccess();

        $result = $this->controller->createExportQueryBuilderPublic($this->adminContext);

        $this->assertInstanceOf(QueryBuilder::class, $result);
    }

    public function testGetExportColumns(): void
    {
        $columns = $this->controller->getExportColumnsPublic($this->adminContext);

        $this->assertIsArray($columns);
        $this->assertNotEmpty($columns);

        /** @var int $index */
        /** @var array{field: string, label: string} $column */
        foreach ($columns as $index => $column) {
            $this->assertIsInt($index);
            $this->assertIsArray($column);
            $this->assertArrayHasKey('field', $column);
            $this->assertArrayHasKey('label', $column);
            $this->assertIsString($column['field']);
            $this->assertIsString($column['label']);
        }

        $fieldNames = array_column($columns, 'field');
        $this->assertContains('title', $fieldNames);
        $this->assertContains('content', $fieldNames);
    }

    public function testShouldSkipFieldForExport(): void
    {
        $textField = TextField::new('name');
        $imageField = ImagePreviewField::new('image');

        $this->assertFalse($this->controller->shouldSkipFieldForExportPublic($textField));
        $this->assertTrue($this->controller->shouldSkipFieldForExportPublic($imageField));
    }

    public function testGetFieldExportType(): void
    {
        $textField = TextField::new('name');
        $dateField = DateTimeField::new('createdAt');
        $boolField = BooleanField::new('active');
        $idField = IdField::new('id');

        $this->assertEquals('string', $this->controller->getFieldExportTypePublic($textField));
        $this->assertEquals('datetime', $this->controller->getFieldExportTypePublic($dateField));
        $this->assertEquals('boolean', $this->controller->getFieldExportTypePublic($boolField));
        $this->assertEquals('number', $this->controller->getFieldExportTypePublic($idField));
    }

    public function testGenerateExportFileName(): void
    {
        $fileName = $this->controller->generateExportFileNamePublic($this->adminContext);

        $this->assertStringStartsWith('testentity_export_', $fileName);
        $this->assertStringEndsWith('.xlsx', $fileName);
        $this->assertMatchesRegularExpression('/testentity_export_\d{14}\.xlsx/', $fileName);
    }

    public function testGetExportRemark(): void
    {
        $remark = $this->controller->getExportRemarkPublic($this->adminContext);

        $this->assertStringStartsWith('TestEntity数据导出 - ', $remark);
        $this->assertMatchesRegularExpression('/TestEntity数据导出 - \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $remark);
    }

    public function testGetExportParameters(): void
    {
        $parameters = $this->controller->getExportParametersPublic($this->adminContext);

        $this->assertIsArray($parameters);
        $this->assertEquals('xlsx', $parameters['format']);
        $this->assertEquals('AsyncExportBundle\Tests\Trait\TestEntity', $parameters['entity_class']);
        $this->assertArrayHasKey('created_at', $parameters);
    }

    public function testGetSearchableFields(): void
    {
        $fields = $this->controller->getSearchableFieldsPublic();

        $this->assertIsArray($fields);
        $this->assertEquals(['title', 'content'], $fields);
    }

    private function setupEntityManagerForSuccess(): void
    {
        /** @var MockObject&EntityRepository<object> */
        $repository = $this->createMock(EntityRepository::class);
        /** @var MockObject&QueryBuilder */
        $queryBuilder = $this->createMock(QueryBuilder::class);
        /** @var MockObject&Query */
        $query = $this->createMock(Query::class);

        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $queryBuilder->method('getDQL')->willReturn('SELECT e FROM TestEntity e');

        $query->method('getDQL')->willReturn('SELECT e FROM TestEntity e');
        $query->method('getResult')->willReturn([
            (object) ['id' => 1, 'title' => 'Test Title 1', 'content' => 'Test Content 1'],
            (object) ['id' => 2, 'title' => 'Test Title 2', 'content' => 'Test Content 2'],
        ]);

        $repository->method('createQueryBuilder')->willReturn($queryBuilder);
        $this->entityManager->method('getRepository')->willReturn($repository);
        $this->entityManager->expects($this->any())->method('persist');
        $this->entityManager->expects($this->any())->method('flush');
    }

    private function setupEntityManagerForException(): void
    {
        $this->entityManager->method('getRepository')
            ->willThrowException(new \Exception('Database error'))
        ;
    }

    /**
     * @param Request $request
     * @param SearchDto $searchDto
     * @return MockObject&AdminContextInterface
     */
    private function createMockAdminContext(Request $request, SearchDto $searchDto): AdminContextInterface
    {
        /** @var MockObject&AdminContextInterface */
        $context = $this->createMock(AdminContextInterface::class);

        // 创建Mock的ClassMetadata，配置必要的字段信息以避免EntityDto警告
        /** @var MockObject&ClassMetadata<object> */
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->method('getName')->willReturn(TestEntity::class);
        $classMetadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $classMetadata->method('hasField')->willReturn(true);
        $classMetadata->method('hasAssociation')->willReturn(false);
        $classMetadata->method('getTypeOfField')->willReturn('string');
        $classMetadata->method('getAssociationNames')->willReturn([]);
        $classMetadata->method('getFieldNames')->willReturn(['id', 'title', 'content']);
        $classMetadata->method('getTableName')->willReturn('test_entity');

        // 创建真实的EntityDto实例，因为它是final类，需要ClassMetadata作为第二个参数
        $entityDto = new EntityDto(TestEntity::class, $classMetadata);

        $context->method('getRequest')->willReturn($request);
        $context->method('getSearch')->willReturn($searchDto);
        $context->method('getEntity')->willReturn($entityDto);

        return $context;
    }
}
