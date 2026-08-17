<?php

declare(strict_types=1);

namespace App\Module\Company\Infrastructure\Persistance\Repository\Doctrine\Employee\Writer;

use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Interface\Employee\EmployeeWriterInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Persistence\ManagerRegistry;

final class EmployeeWriterRepository extends ServiceEntityRepository implements EmployeeWriterInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function saveEmployee(Employee $employee): void
    {
        $this->getEntityManager()->persist($employee);
        $this->getEntityManager()->flush();
    }

    public function saveEmployeesInDB(Collection $employees): void
    {
        foreach ($employees as $employee) {
            $this->getEntityManager()->persist($employee);
        }
        $this->getEntityManager()->flush();
    }

    public function deleteEmployeeInDB(Employee $employee): void
    {
        $this->getEntityManager()->remove($employee);
        $this->getEntityManager()->flush();
    }

    public function deleteMultipleEmployeesInDB(Collection $employees): void
    {
        if (empty($employees)) {
            return;
        }

        foreach ($employees as $employee) {
            $this->getEntityManager()->remove($employee);
        }

        $this->getEntityManager()->flush();
    }
}
