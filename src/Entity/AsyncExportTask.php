<?php

declare(strict_types=1);

namespace AsyncExportBundle\Entity;

use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;

#[ORM\Entity(repositoryClass: AsyncExportTaskRepository::class)]
#[ORM\Table(name: 'curd_export_task', options: ['comment' => '异步：导出任务'])]
class AsyncExportTask implements \Stringable
{
    use SnowflakeKeyAware;
    use TimestampableAware;
    use BlameableAware;

    #[ORM\ManyToOne(targetEntity: UserInterface::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?UserInterface $user = null;

    #[Assert\Length(max: 1000)]
    #[ORM\Column(type: Types::STRING, length: 1000, nullable: true, options: ['comment' => '文件名'])]
    private ?string $file = null;

    /**
     * @var string|null 这里我们主要记录的是主要使用的那个实体类，我们使用这个来查找 EntityManager
     */
    #[Assert\Length(max: 1000)]
    #[ORM\Column(type: Types::STRING, length: 1000, nullable: true, options: ['comment' => '实体类名'])]
    private ?string $entityClass = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 65535)]
    #[ORM\Column(type: Types::TEXT, options: ['comment' => 'DQL'])]
    private ?string $dql = null;

    /**
     * @var array<int, array<string, mixed>>
     */
    #[Assert\Type(type: 'array')]
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '字段配置'])]
    private array $columns = [];

    #[Assert\Length(max: 65535)]
    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '备注'])]
    private ?string $remark = null;

    #[Assert\Length(max: 65535)]
    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '异常信息'])]
    private ?string $exception = null;

    /**
     * @var array<string, mixed>
     */
    #[Assert\Type(type: 'array')]
    #[ORM\Column(type: Types::JSON, options: ['comment' => '参数信息'])]
    private array $json = [];

    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['default' => 0, 'comment' => '总行数'])]
    private ?int $totalCount = 0;

    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['default' => 0, 'comment' => '已处理'])]
    private ?int $processCount = 0;

    #[Assert\PositiveOrZero]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['default' => 0, 'comment' => '内存占用'])]
    private ?int $memoryUsage = 0;

    #[Assert\Type(type: 'bool')]
    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '有效', 'default' => 0])]
    private ?bool $valid = false;

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): void
    {
        $this->user = $user;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(?string $file): void
    {
        $this->file = $file;
    }

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function setEntityClass(?string $entityClass): void
    {
        $this->entityClass = $entityClass;
    }

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): void
    {
        $this->remark = $remark;
    }

    public function getDql(): ?string
    {
        return $this->dql;
    }

    public function setDql(string $dql): void
    {
        $this->dql = $dql;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    public function setColumns(array $columns): void
    {
        $this->columns = $columns;
    }

    public function getException(): ?string
    {
        return $this->exception;
    }

    public function setException(?string $exception): void
    {
        $this->exception = $exception;
    }

    /**
     * @return array<string, mixed>
     */
    public function getJson(): array
    {
        return $this->json;
    }

    /**
     * @param array<string, mixed> $json
     */
    public function setJson(array $json): void
    {
        $this->json = $json;
    }

    public function getTotalCount(): ?int
    {
        return $this->totalCount;
    }

    public function setTotalCount(?int $totalCount): void
    {
        $this->totalCount = $totalCount;
    }

    public function getProcessCount(): ?int
    {
        return $this->processCount;
    }

    public function setProcessCount(?int $processCount): void
    {
        $this->processCount = $processCount;
    }

    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    public function setMemoryUsage(?int $memoryUsage): void
    {
        $this->memoryUsage = $memoryUsage;
    }

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(?bool $valid): void
    {
        $this->valid = $valid;
    }

    public function __toString(): string
    {
        return sprintf('AsyncExportTask #%s', $this->getId() ?? 'new');
    }

    /**
     * 虚拟getter用于EasyAdmin显示字段配置
     */
    public function getColumnsInfo(): string
    {
        return $this->formatColumnsForDisplay($this->columns);
    }

    /**
     * 虚拟getter用于EasyAdmin显示任务状态
     */
    public function getTaskStatus(): string
    {
        if (null === $this->totalCount || $this->totalCount <= 0) {
            return '等待中';
        }

        if (null === $this->processCount || $this->processCount <= 0) {
            return '准备中';
        }

        if ($this->processCount >= $this->totalCount) {
            return '已完成';
        }

        $percentage = round(($this->processCount / $this->totalCount) * 100, 1);

        return sprintf('%s%% (%d/%d)', $percentage, $this->processCount, $this->totalCount);
    }

    /**
     * @param array<int, array<string, mixed>> $columns
     */
    private function formatColumnsForDisplay(array $columns): string
    {
        if (0 === count($columns)) {
            return '无字段配置';
        }

        $count = count($columns);
        if ($count <= 3) {
            $names = array_column($columns, 'property');

            return sprintf('%d个字段: %s', $count, implode(', ', $names));
        }

        $names = array_slice(array_column($columns, 'property'), 0, 3);

        return sprintf('%d个字段: %s...', $count, implode(', ', $names));
    }
}
