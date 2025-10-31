<?php

declare(strict_types=1);

namespace AsyncExportBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class AsyncExportExtension extends AutoExtension implements PrependExtensionInterface
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }

    public function prepend(ContainerBuilder $container): void
    {
        // 获取Bundle的实际路径
        $bundleDir = dirname(__DIR__, 2);
        $entityDir = $bundleDir . '/src/Entity';

        // 配置Doctrine实体映射
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'AsyncExportBundle' => [
                        'is_bundle' => false,
                        'type' => 'attribute',
                        'dir' => $entityDir,
                        'prefix' => 'AsyncExportBundle\Entity',
                        'alias' => 'AsyncExport',
                    ],
                ],
            ],
        ]);
    }
}
