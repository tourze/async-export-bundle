<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Service;

use AsyncExportBundle\Service\ExportDisplayFormatter;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
#[CoversClass(ExportDisplayFormatter::class)]
final class ExportDisplayFormatterTest extends TestCase
{
    private ExportDisplayFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new ExportDisplayFormatter();
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function formatUserValueProvider(): array
    {
        return [
            'null value' => [null, ''],
            'string value' => ['test_string', ''],
        ];
    }

    #[DataProvider('formatUserValueProvider')]
    public function testFormatUserValue(mixed $value, string $expected): void
    {
        $result = $this->formatter->formatUserValue($value);

        $this->assertSame($expected, $result);
    }

    public function testFormatUserValueWithMockUser(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('test_identifier');

        $result = $this->formatter->formatUserValue($user);

        $this->assertSame('test_identifier', $result);
    }

    public function testGetFileFieldsForIndexPage(): void
    {
        $fields = $this->formatter->getFileFields(Crud::PAGE_INDEX);

        $this->assertCount(2, $fields);
        $this->assertInstanceOf(TextField::class, $fields[0]);
        $this->assertInstanceOf(TextField::class, $fields[1]);
    }

    public function testGetFileFieldsForDetailPage(): void
    {
        $fields = $this->formatter->getFileFields(Crud::PAGE_DETAIL);

        $this->assertGreaterThanOrEqual(3, count($fields)); // file + detail fields
        $this->assertInstanceOf(TextField::class, $fields[0]);
    }

    public function testGetProgressFieldsForIndexPage(): void
    {
        $fields = $this->formatter->getProgressFields(Crud::PAGE_INDEX);

        $this->assertCount(1, $fields);
        $this->assertInstanceOf(BooleanField::class, $fields[0]);
    }

    public function testGetProgressFieldsForDetailPage(): void
    {
        $fields = $this->formatter->getProgressFields(Crud::PAGE_DETAIL);

        $this->assertCount(2, $fields);
        $this->assertInstanceOf(IntegerField::class, $fields[0]);
        $this->assertInstanceOf(IntegerField::class, $fields[1]);
    }

    public function testGetMetaFields(): void
    {
        $fields = $this->formatter->getMetaFields();

        $this->assertCount(2, $fields);
        $this->assertInstanceOf(TextareaField::class, $fields[0]);
        $this->assertInstanceOf(TextareaField::class, $fields[1]);
    }

    public function testGetTimestampFields(): void
    {
        $fields = $this->formatter->getTimestampFields();

        $this->assertCount(4, $fields);
        $this->assertInstanceOf(DateTimeField::class, $fields[0]);
        $this->assertInstanceOf(DateTimeField::class, $fields[1]);
        $this->assertInstanceOf(TextField::class, $fields[2]);
        $this->assertInstanceOf(TextField::class, $fields[3]);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function formatBytesProvider(): array
    {
        return [
            'zero bytes' => [0, '0 B'],
            'null value' => [null, '0 B'],
            'string value' => ['invalid', '0 B'],
            'bytes' => [512, '512 B'],
            'kilobytes' => [1536, '1.5 KB'],
            'megabytes' => [1572864, '1.5 MB'],
            'gigabytes' => [1610612736, '1.5 GB'],
            'terabytes' => [1649267441664, '1.5 TB'],
        ];
    }

    #[DataProvider('formatBytesProvider')]
    public function testFormatBytes(mixed $value, string $expected): void
    {
        $result = $this->formatter->formatBytes($value);

        $this->assertSame($expected, $result);
    }
}
