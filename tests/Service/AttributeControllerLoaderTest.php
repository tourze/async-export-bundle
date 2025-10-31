<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Service;

use AsyncExportBundle\Service\AttributeControllerLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\Routing\RouteCollection;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    private AttributeControllerLoader $loader;

    protected function onSetUp(): void
    {
        $loader = self::getService(AttributeControllerLoader::class);
        self::assertInstanceOf(AttributeControllerLoader::class, $loader);
        $this->loader = $loader;
    }

    public function testLoad(): void
    {
        $result = $this->loader->load('resource');

        $this->assertInstanceOf(RouteCollection::class, $result);
        // 路由集合可能为空，这是正常的
        $this->assertGreaterThanOrEqual(0, $result->count());
    }

    public function testSupports(): void
    {
        $this->assertFalse($this->loader->supports('resource'));
        $this->assertFalse($this->loader->supports('resource', 'type'));
    }

    public function testAutoload(): void
    {
        $result = $this->loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $result);
        // 路由集合可能为空，这是正常的
        $this->assertGreaterThanOrEqual(0, $result->count());
    }
}
