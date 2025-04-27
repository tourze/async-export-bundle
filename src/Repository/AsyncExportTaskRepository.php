<?php

namespace AsyncExportBundle\Repository;

use AsyncExportBundle\Entity\AsyncExportTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AsyncExportTask>
 *
 * @method AsyncExportTask|null find($id, $lockMode = null, $lockVersion = null)
 * @method AsyncExportTask|null findOneBy(array $criteria, array $orderBy = null)
 * @method AsyncExportTask[]    findAll()
 * @method AsyncExportTask[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class AsyncExportTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AsyncExportTask::class);
    }
}
