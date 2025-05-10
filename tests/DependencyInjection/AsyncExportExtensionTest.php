<?php

namespace AsyncExportBundle\Tests\DependencyInjection;

use AsyncExportBundle\DependencyInjection\AsyncExportExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AsyncExportExtensionTest extends TestCase
{
    private AsyncExportExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new AsyncExportExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoad(): void
    {
        $this->extension->load([], $this->container);
        
        $this->assertTrue(
            file_exists(__DIR__ . '/../../src/Resources/config/services.yaml'),
            'services.yaml 配置文件应该存在'
        );
        
        $this->assertTrue(
            $this->container->hasDefinition('AsyncExportBundle\Repository\AsyncExportTaskRepository') ||
            $this->container->hasAlias('AsyncExportBundle\Repository\AsyncExportTaskRepository'),
            'AsyncExportTaskRepository 服务应该被注册'
        );
    }

    public function testLoadWithEmptyConfigs(): void
    {
        $this->extension->load([], $this->container);
        
        $this->assertNotEmpty(
            $this->container->getDefinitions(),
            'Container 不应为空，即使没有配置'
        );
    }
} 