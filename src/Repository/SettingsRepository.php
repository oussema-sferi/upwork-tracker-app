<?php

namespace App\Repository;

use App\Entity\Settings;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settings>
 */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    public function save(Settings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Settings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByUser(User $user): ?Settings
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findOrCreateByUser(User $user): Settings
    {
        $settings = $this->findByUser($user);
        
        if (!$settings) {
            $settings = new Settings();
            $settings->setUser($user);
            $this->save($settings, true);
        }
        
        return $settings;
    }
}
