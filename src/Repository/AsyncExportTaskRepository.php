<?php

declare(strict_types=1);

namespace AsyncExportBundle\Repository;

use AsyncExportBundle\Entity\AsyncExportTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<AsyncExportTask>
 */
#[AsRepository(entityClass: AsyncExportTask::class)]
class AsyncExportTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AsyncExportTask::class);
    }

    public function save(AsyncExportTask $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AsyncExportTask $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * 查找待处理的任务
     * @return array<AsyncExportTask>
     */
    public function findPendingTasks(int $limit = 10, bool $includeCompleted = false): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.createTime', 'ASC')
            ->setMaxResults($limit)
        ;

        if (!$includeCompleted) {
            $qb->where('t.valid = :valid OR t.valid IS NULL')
                ->andWhere('t.totalCount IS NULL OR t.processCount < t.totalCount OR t.processCount IS NULL')
                ->setParameter('valid', false)
            ;
        }

        /** @var array<AsyncExportTask> */
        return $qb->getQuery()->getResult();
    }

    /**
     * 查找已完成的任务
     * @return array<AsyncExportTask>
     */
    public function findCompletedTasks(int $limit = 100): array
    {
        /** @var array<AsyncExportTask> */
        return $this->createQueryBuilder('t')
            ->where('t.valid = :valid')
            ->andWhere('t.totalCount > 0')
            ->andWhere('t.processCount >= t.totalCount')
            ->setParameter('valid', true)
            ->orderBy('t.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }
}
