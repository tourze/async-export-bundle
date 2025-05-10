<?php

namespace AsyncExportBundle\Tests;

use AsyncExportBundle\AsyncExportBundle;
use PHPUnit\Framework\TestCase;

class AsyncExportBundleTest extends TestCase
{
    public function testInstance(): void
    {
        $bundle = new AsyncExportBundle();
        
        $this->assertInstanceOf(AsyncExportBundle::class, $bundle);
    }
} 