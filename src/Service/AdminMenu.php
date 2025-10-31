<?php

declare(strict_types=1);

namespace AsyncExportBundle\Service;

use AsyncExportBundle\Entity\AsyncExportTask;
use Knp\Menu\ItemInterface;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

/**
 * 异步导出菜单服务
 */
readonly class AdminMenu implements MenuProviderInterface
{
    public function __construct(
        private LinkGeneratorInterface $linkGenerator,
    ) {
    }

    public function __invoke(ItemInterface $item): void
    {
        if (null === $item->getChild('系统管理')) {
            $item->addChild('系统管理');
        }

        $systemMenu = $item->getChild('系统管理');
        if (null === $systemMenu) {
            return;
        }

        // 导出任务菜单
        $systemMenu->addChild('导出任务')
            ->setUri($this->linkGenerator->getCurdListPage(AsyncExportTask::class))
            ->setAttribute('icon', 'fas fa-download')
        ;
    }
}
