<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Trait;

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Trait\AsyncExportableTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminCrud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Security\Core\User\UserInterface;
use Tourze\EasyAdminImagePreviewFieldBundle\Field\ImagePreviewField;
use UnitEnum;

/**
 * 测试用的控制器类
 *
 * @extends AbstractCrudController<TestEntity>
 */
#[AdminCrud(routePath: '/test', routeName: 'test')]
class TestCrudController extends AbstractCrudController
{
    use AsyncExportableTrait;

    private EntityManagerInterface $entityManager;

    private ?UserInterface $user = null;

    /** @var array<int, array{type: string, message: mixed}> */
    private array $flashMessages = [];

    public static function getEntityFqcn(): string
    {
        return TestEntity::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id'),
            TextField::new('title'),
            TextField::new('content'),
            DateTimeField::new('createdAt'),
            BooleanField::new('active'),
            ImagePreviewField::new('image'),
        ];
    }

    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    public function setUser(?UserInterface $user): void
    {
        $this->user = $user;
    }

    public function setAdminContext(AdminContextInterface $adminContext): void
    {
        // 不需要保存，因为测试时会直接传入context
    }

    protected function getUser(): ?UserInterface
    {
        return $this->user;
    }

    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    protected function createExportQueryBuilder(AdminContextInterface $context): QueryBuilder
    {
        $repository = $this->entityManager->getRepository(TestEntity::class);

        return $repository->createQueryBuilder('e');
    }

    public function get(string $id): object
    {
        if ('router' === $id) {
            // 简化的路由生成器，不使用Mock
            return new class implements UrlGeneratorInterface {
                /**
                 * @param array $parameters
                 * @phpstan-ignore-next-line missingType.iterableValue
                 */
                public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
                {
                    return '/admin';
                }

                public function setContext(RequestContext $context): void
                {
                }

                public function getContext(): RequestContext
                {
                    return new RequestContext();
                }
            };
        }

        throw new \InvalidArgumentException("Service '{$id}' not found");
    }

    protected function addFlash(string $type, mixed $message): void
    {
        $this->flashMessages[] = ['type' => $type, 'message' => $message];
    }

    public function hasFlashMessage(string $type, ?string $message = null): bool
    {
        foreach ($this->flashMessages as $flash) {
            if ($flash['type'] === $type) {
                if (null === $message) {
                    return true;
                }
                $flashMessage = is_string($flash['message']) ? $flash['message'] : '';
                if (str_contains($flashMessage, $message)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function redirect(string $url, int $status = 302): RedirectResponse
    {
        return new RedirectResponse($url, $status);
    }

    /**
     * @param array $parameters
     * @phpstan-ignore-next-line missingType.iterableValue
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse
    {
        return new RedirectResponse('/admin', $status);
    }

    /**
     * @param array $parameters
     * @phpstan-ignore-next-line missingType.iterableValue
     */
    protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return '/admin';
    }

    /**
     * @return \UnitEnum|array<string, mixed>|string|int|float|bool|null
     */
    protected function getParameter(string $name): \UnitEnum|array|string|int|float|bool|null
    {
        return match ($name) {
            'kernel.project_dir' => sys_get_temp_dir() . '/test-project',
            default => null,
        };
    }

    // 公开内部方法以便测试
    public function createAsyncExportTaskPublic(UserInterface $user, AdminContextInterface $context): AsyncExportTask
    {
        return $this->createAsyncExportTask($user, $context);
    }

    public function createExportQueryBuilderPublic(AdminContextInterface $context): QueryBuilder
    {
        return $this->createExportQueryBuilder($context);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getExportColumnsPublic(AdminContextInterface $context): array
    {
        return $this->getExportColumns($context);
    }

    /**
     * @param mixed $field
     */
    public function shouldSkipFieldForExportPublic($field): bool
    {
        return $this->shouldSkipFieldForExport($field);
    }

    /**
     * @param mixed $field
     */
    public function getFieldExportTypePublic($field): string
    {
        return $this->getFieldExportType($field);
    }

    public function generateExportFileNamePublic(AdminContextInterface $context): string
    {
        return $this->generateExportFileName($context);
    }

    public function getExportRemarkPublic(AdminContextInterface $context): string
    {
        return $this->getExportRemark($context);
    }

    /**
     * @return array<string, mixed>
     */
    public function getExportParametersPublic(AdminContextInterface $context): array
    {
        return $this->getExportParameters($context);
    }

    /**
     * @return array<int, string>
     */
    public function getSearchableFieldsPublic(): array
    {
        return $this->getSearchableFields();
    }

    /**
     * @return array<int, string>
     */
    protected function getSearchableFields(): array
    {
        return ['title', 'content'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getExportColumns(?AdminContextInterface $context = null): array
    {
        return [
            ['field' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['field' => 'title', 'label' => '标题', 'type' => 'string'],
            ['field' => 'content', 'label' => '内容', 'type' => 'string'],
        ];
    }
}
