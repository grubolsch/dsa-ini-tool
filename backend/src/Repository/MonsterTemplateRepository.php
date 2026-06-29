<?php

namespace App\Repository;

use App\Entity\MonsterTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MonsterTemplate>
 */
class MonsterTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MonsterTemplate::class);
    }

    /**
     * @return MonsterTemplate[]
     */
    public function search(?string $q): array
    {
        $qb = $this->createQueryBuilder('m')->orderBy('m.name', 'ASC');

        if (null !== $q && '' !== trim($q)) {
            $qb->andWhere('LOWER(m.name) LIKE :q')
                ->setParameter('q', '%'.mb_strtolower(trim($q)).'%');
        }

        return $qb->getQuery()->getResult();
    }
}
