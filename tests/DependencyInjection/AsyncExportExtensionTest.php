<?php

namespace AsyncExportBundle\Tests\DependencyInjection;

use AsyncExportBundle\DependencyInjection\AsyncExportExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncExportExtension::class)]
final class AsyncExportExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    protected function getExtensionClass(): string
    {
        return AsyncExportExtension::class;
    }

    public function testPrepend(): void
    {
        $container = new ContainerBuilder();
        $extension = new AsyncExportExtension();

        $extension->prepend($container);

        // 验证Doctrine配置已添加
        $configs = $container->getExtensionConfig('doctrine');
        self::assertNotEmpty($configs);

        // 检查映射配置 - 使用assertIsArray同时完成类型检查和类型窄化
        $firstConfig = $configs[0];
        self::assertIsArray($firstConfig);
        self::assertArrayHasKey('orm', $firstConfig);

        $ormConfig = $firstConfig['orm'];
        self::assertIsArray($ormConfig);
        self::assertArrayHasKey('mappings', $ormConfig);

        $mappings = $ormConfig['mappings'];
        self::assertIsArray($mappings);
        self::assertArrayHasKey('AsyncExportBundle', $mappings);

        $bundleMapping = $mappings['AsyncExportBundle'];
        self::assertIsArray($bundleMapping);
        self::assertFalse($bundleMapping['is_bundle']);
        self::assertSame('attribute', $bundleMapping['type']);
        self::assertSame('AsyncExportBundle\Entity', $bundleMapping['prefix']);
        self::assertSame('AsyncExport', $bundleMapping['alias']);

        self::assertIsString($bundleMapping['dir']);
        self::assertStringContainsString('/src/Entity', $bundleMapping['dir']);
    }
}
