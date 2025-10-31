<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Exception;

use AsyncExportBundle\Exception\AsyncExportException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(AsyncExportException::class)]
final class AsyncExportExceptionTest extends AbstractExceptionTestCase
{
    public function testUnexpectedRepositoryClassCreatesCorrectException(): void
    {
        $className = 'Some\Unknown\Class';
        $exception = AsyncExportException::unexpectedRepositoryClass($className);

        self::assertSame("Unexpected repository class: {$className}", $exception->getMessage());
    }

    public function testExceptionInheritance(): void
    {
        $reflection = new \ReflectionClass(AsyncExportException::class);

        self::assertTrue($reflection->isSubclassOf(\RuntimeException::class));
    }
}
