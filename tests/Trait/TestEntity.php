<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Trait;

/**
 * 测试用的实体类
 */
class TestEntity
{
    public int $id;

    public string $name;

    public string $title;

    public string $content;

    public \DateTime $createdAt;

    public bool $active;

    public string $image;
}
