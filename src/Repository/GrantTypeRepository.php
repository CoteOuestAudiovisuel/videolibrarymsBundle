<?php

namespace Coa\VideolibraryBundle\Repository;

use Coa\VideolibraryBundle\Entity\GrantType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method GrantType|null find($id, $lockMode = null, $lockVersion = null)
 * @method GrantType|null findOneBy(array $criteria, array $orderBy = null)
 * @method GrantType[]    findAll()
 * @method GrantType[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class GrantTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GrantType::class);
    }
}