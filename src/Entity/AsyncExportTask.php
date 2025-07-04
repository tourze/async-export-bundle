<?php

namespace AsyncExportBundle\Entity;

use AsyncExportBundle\Repository\AsyncExportTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
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

    #[ORM\Column(type: Types::STRING, length: 1000, nullable: true, options: ['comment' => '文件名'])]
    private ?string $file = null;

    /**
     * @var string|null 这里我们主要记录的是主要使用的那个实体类，我们使用这个来查找 EntityManager
     */
    #[ORM\Column(type: Types::STRING, length: 1000, nullable: true, options: ['comment' => '实体类名'])]
    private ?string $entityClass = null;

    #[ORM\Column(type: Types::TEXT, options: ['comment' => 'DQL'])]
    private ?string $dql = null;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '字段配置'])]
    private array $columns = [];

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '备注'])]
    private ?string $remark = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '异常信息'])]
    private ?string $exception = null;

    #[ORM\Column(nullable: true, options: ['comment' => '参数信息'])]
    private ?array $json = [];

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['default' => 0, 'comment' => '总行数'])]
    private ?int $totalCount = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['default' => 0, 'comment' => '已处理'])]
    private ?int $processCount = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['default' => 0, 'comment' => '内存占用'])]
    private ?int $memoryUsage = 0;

    #[IndexColumn]
    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '有效', 'default' => 0])]
    private ?bool $valid = false;



    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getFile(): ?string
    {
        return $this->file;
    }

    public function setFile(string $file): self
    {
        $this->file = $file;

        return $this;
    }

    public function getEntityClass(): ?string
    {
        return $this->entityClass;
    }

    public function setEntityClass(?string $entityClass): self
    {
        $this->entityClass = $entityClass;

        return $this;
    }

    public function getRemark(): ?string
    {
        return $this->remark;
    }

    public function setRemark(?string $remark): self
    {
        $this->remark = $remark;

        return $this;
    }

    public function getDql(): ?string
    {
        return $this->dql;
    }

    public function setDql(string $dql): self
    {
        $this->dql = $dql;

        return $this;
    }

    public function getColumns(): ?array
    {
        return $this->columns;
    }

    public function setColumns(array $columns): self
    {
        $this->columns = $columns;

        return $this;
    }

    public function getException(): ?string
    {
        return $this->exception;
    }

    public function setException(?string $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public function getJson(): ?array
    {
        return $this->json;
    }

    public function setJson(?array $json): self
    {
        $this->json = $json;

        return $this;
    }

    public function getTotalCount(): ?int
    {
        return $this->totalCount;
    }

    public function setTotalCount(?int $totalCount): self
    {
        $this->totalCount = $totalCount;

        return $this;
    }

    public function getProcessCount(): ?int
    {
        return $this->processCount;
    }

    public function setProcessCount(?int $processCount): self
    {
        $this->processCount = $processCount;

        return $this;
    }

    public function getMemoryUsage(): ?int
    {
        return $this->memoryUsage;
    }

    public function setMemoryUsage(?int $memoryUsage): self
    {
        $this->memoryUsage = $memoryUsage;

        return $this;
    }

    public function isValid(): ?bool
    {
        return $this->valid;
    }

    public function setValid(?bool $valid): self
    {
        $this->valid = $valid;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('AsyncExportTask #%s', $this->getId() ?? 'new');
    }
}
