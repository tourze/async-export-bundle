<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests;

use AsyncExportBundle\AsyncExportBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 * @phpstan-ignore symplify.forbiddenExtendOfNonAbstractClass
 */
#[CoversClass(AsyncExportBundle::class)]
#[RunTestsInSeparateProcesses]
final class AsyncExportBundleTest extends AbstractBundleTestCase
{
}
