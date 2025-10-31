<?php

declare(strict_types=1);

namespace AsyncExportBundle\Tests\Service;

use AsyncExportBundle\Entity\AsyncExportTask;
use AsyncExportBundle\Service\TaskDisplayService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @internal
 */
#[CoversClass(TaskDisplayService::class)]
final class TaskDisplayServiceTest extends TestCase
{
    private TaskDisplayService $service;

    protected function setUp(): void
    {
        $this->service = new TaskDisplayService();
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
        $result = $this->service->formatUserValue($value);

        $this->assertSame($expected, $result);
    }

    public function testFormatUserValueWithMockUser(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('test_identifier');

        $result = $this->service->formatUserValue($user);

        $this->assertSame('test_identifier', $result);
    }

    /**
     * @return array<string, array{?string, bool, int, int, array<int, array<string, mixed>>, string}>
     */
    public static function formatFileInfoProvider(): array
    {
        return [
            'no file' => [null, true, 100, 100, [], '未生成'],
            'empty file' => ['', true, 100, 100, [], '未生成'],
            'incomplete task' => ['test.csv', true, 100, 50, [], 'test.csv'],
            'completed task' => ['completed.xlsx', true, 100, 100, [], 'completed.xlsx <br><small><i class="fas fa-check-circle text-success"></i> 可下载</small>'],
            'invalid task' => ['invalid.csv', false, 100, 100, [], 'invalid.csv'],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    #[DataProvider('formatFileInfoProvider')]
    public function testFormatFileInfo(?string $file, bool $valid, int $total, int $processed, array $columns, string $expected): void
    {
        $task = $this->createMockTask($file, $valid, $total, $processed, $columns);

        $result = $this->service->formatFileInfo($task);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{array<int, array<string, mixed>>, string}>
     */
    public static function formatColumnsInfoProvider(): array
    {
        return [
            'empty columns' => [[], '未配置'],
            'single column' => [[['field' => 'name', 'label' => '姓名']], '1个字段: name'],
            'three columns' => [
                [
                    ['field' => 'name', 'label' => '姓名'],
                    ['field' => 'email', 'label' => '邮箱'],
                    ['field' => 'phone', 'label' => '电话'],
                ],
                '3个字段: name, email, phone',
            ],
            'more than three columns' => [
                [
                    ['field' => 'name', 'label' => '姓名'],
                    ['field' => 'email', 'label' => '邮箱'],
                    ['field' => 'phone', 'label' => '电话'],
                    ['field' => 'address', 'label' => '地址'],
                ],
                '4个字段: name, email, phone...',
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    #[DataProvider('formatColumnsInfoProvider')]
    public function testFormatColumnsInfo(array $columns, string $expected): void
    {
        $task = $this->createMockTask('test.csv', true, 100, 100, $columns);

        $result = $this->service->formatColumnsInfo($task);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{bool, int, int, string}>
     */
    public static function formatTaskStatusProvider(): array
    {
        return [
            'invalid task' => [false, 100, 100, '<span class="badge badge-danger">无效</span>'],
            'waiting task' => [true, 0, 0, '<span class="badge badge-warning">等待处理</span>'],
            'completed task' => [true, 100, 100, '<span class="badge badge-success">已完成</span>'],
            'in progress task' => [true, 100, 50, '<span class="badge badge-info">进行中 50%</span>'],
            'partial progress' => [true, 100, 25, '<span class="badge badge-info">进行中 25%</span>'],
        ];
    }

    #[DataProvider('formatTaskStatusProvider')]
    public function testFormatTaskStatus(bool $valid, int $total, int $processed, string $expected): void
    {
        $task = $this->createMockTask('test.csv', $valid, $total, $processed, []);

        $result = $this->service->formatTaskStatus($task);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function formatJsonDataProvider(): array
    {
        return [
            'null data' => [null, '{}'],
            'empty array' => [[], '[]'],
            'simple object' => [['key' => 'value'], "{\n    \"key\": \"value\"\n}"],
            'complex object' => [
                ['name' => '测试', 'count' => 100],
                "{\n    \"name\": \"测试\",\n    \"count\": 100\n}",
            ],
        ];
    }

    #[DataProvider('formatJsonDataProvider')]
    public function testFormatJsonData(mixed $data, string $expected): void
    {
        $result = $this->service->formatJsonData($data);

        $this->assertSame($expected, $result);
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
        $result = $this->service->formatBytes($value);

        $this->assertSame($expected, $result);
    }

    /**
     * @return array<string, array{bool, int, int, bool}>
     */
    public static function isTaskCompletedProvider(): array
    {
        return [
            'invalid task' => [false, 100, 100, false],
            'valid but no total count' => [true, 0, 0, false],
            'valid but not completed' => [true, 100, 50, false],
            'valid and completed' => [true, 100, 100, true],
            'valid and over completed' => [true, 100, 150, true],
        ];
    }

    #[DataProvider('isTaskCompletedProvider')]
    public function testIsTaskCompleted(bool $valid, int $total, int $processed, bool $expected): void
    {
        $task = $this->createMockTask('test.csv', $valid, $total, $processed, []);

        $result = $this->service->isTaskCompleted($task);

        $this->assertSame($expected, $result);
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    private function createMockTask(
        ?string $file = null,
        bool $valid = true,
        int $totalCount = 0,
        int $processCount = 0,
        array $columns = [],
    ): AsyncExportTask {
        $task = $this->createMock(AsyncExportTask::class);
        $task->method('getFile')->willReturn($file);
        $task->method('isValid')->willReturn($valid);
        $task->method('getTotalCount')->willReturn($totalCount);
        $task->method('getProcessCount')->willReturn($processCount);
        $task->method('getColumns')->willReturn($columns);

        return $task;
    }
}
