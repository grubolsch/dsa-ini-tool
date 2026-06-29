<?php

namespace App\Repository;

use App\Entity\LiveEncounter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LiveEncounter>
 */
class LiveEncounterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveEncounter::class);
    }

    public function findOneByCode(string $code): ?LiveEncounter
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function codeExists(string $code): bool
    {
        return null !== $this->findOneBy(['code' => $code]);
    }
}
