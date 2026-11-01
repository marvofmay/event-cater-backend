<?php

declare(strict_types=1);

namespace App\Module\Company\Infrastructure\Persistance\Repository\Doctrine\Employee\Reader;

use App\Common\Domain\Exception\NotFindByUUIDException;
use App\Module\Company\Domain\Entity\Address;
use App\Module\Company\Domain\Entity\Contact;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Entity\User;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EmployeeReaderRepository extends ServiceEntityRepository implements EmployeeReaderInterface
{
    public function __construct(ManagerRegistry $registry, private readonly TranslatorInterface $translator)
    {
        parent::__construct($registry, Employee::class);
    }

    public function getEmployeeByUUID(string $uuid): ?Employee
    {
        $employee = $this->findOneBy([Employee::COLUMN_UUID => $uuid]);
        if (null === $employee) {
            throw new \Exception($this->translator->trans('employee.uuid.notExists', [':uuid' => $uuid], 'employees'), Response::HTTP_NOT_FOUND);
        }

        return $employee;
    }

    public function getEmployeesByUUID(array $selectedUUID): Collection
    {
        if (empty($selectedUUID)) {
            return new ArrayCollection();
        }

        $employees = $this->getEntityManager()
            ->createQuery('SELECT e FROM '.Employee::class.' e WHERE e.'.Employee::COLUMN_UUID.' IN (:uuids)')
            ->setParameter('uuids', $selectedUUID)
            ->getResult();

        if (count($employees) !== count($selectedUUID)) {
            $missingUuids = array_diff($selectedUUID, array_map(fn ($employee) => $employee->getUUID(), $employees));
            throw new NotFindByUUIDException(sprintf('%s : %s', $this->translator->trans('employee.uuid.notFound', [], 'employees'), implode(', ', $missingUuids)));
        }

        return new ArrayCollection($employees);
    }

    public function isEmployeeWithUUIDExists(string $uuid): bool
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('e')
            ->from(Employee::class, 'e')
            ->where('e.'.Employee::COLUMN_UUID.' = :uuid')
            ->setParameter('uuid', $uuid);

        return null !== $qb->getQuery()->getOneOrNullResult();
    }

    public function getEmployeeByEmail(string $email, ?string $uuid = null): ?User
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('u')
            ->from(User::class, 'u')
            ->where('u.'.User::COLUMN_EMAIL.' = :email')
            ->setParameter('email', $email);

        if (null !== $uuid) {
            $qb->andWhere('(u.'.User::COLUMN_EMPLOYEE_UUID.' IS NULL OR u.'.User::COLUMN_EMPLOYEE_UUID.' != :uuid)')
                ->setParameter('uuid', $uuid);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function isEmployeeWithEmailExists(string $email, ?string $uuid = null): bool
    {
        return !is_null($this->getEmployeeByEmail($email, $uuid));
    }

    public function isEmployeeWithPESELExists(string $pesel, ?string $uuid = null): bool
    {
        return !is_null($this->getEmployeeByPESEL($pesel, $uuid));
    }

    public function getEmployeeByPESEL(string $pesel, ?string $uuid = null): ?Employee
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select(Employee::ALIAS)
            ->from(Employee::class, Employee::ALIAS)
            ->where(sprintf('%s.%s = :pesel', Employee::ALIAS, Employee::COLUMN_PESEL))
            ->setParameter('pesel', $pesel);

        if (null !== $uuid) {
            $qb->andWhere(sprintf('%s.uuid != :uuid', Employee::ALIAS))
                ->setParameter('uuid', $uuid);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function getEmployeesByPESEL(array $selectedPESEL): Collection
    {
        if ([] === $selectedPESEL) {
            return new ArrayCollection();
        }

        $employees = $this->getEntityManager()
            ->createQuery(
                'SELECT e FROM '.Employee::class.' e WHERE e.pesel IN (:pesels)'
            )
            ->setParameter('pesels', $selectedPESEL)
            ->getResult();

        return new ArrayCollection($employees);
    }

    public function isEmployeeAlreadyExistsWithEmailOrPESEL(string $pesel, string $email, ?string $uuid): bool
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.contacts', 'c')
            ->andWhere('e.pesel = :pesel OR (c.type = :type AND c.data = (:email))')
            ->setParameter('pesel', $pesel)
            ->setParameter('type', 'email')
            ->setParameter('email', $email);

        if (null !== $uuid) {
            $qb->andWhere('e.uuid != :uuid')
                ->setParameter('uuid', $uuid);
        }

        return (bool) $qb->getQuery()->getOneOrNullResult();
    }

    public function getDeletedEmployeeByUUID(string $uuid): ?Employee
    {
        $filters = $this->getEntityManager()->getFilters();
        $filters->disable('soft_delete');

        try {
            $deletedEmployee = $this->createQueryBuilder(Employee::ALIAS)
                ->where(Employee::ALIAS.'.'.Employee::COLUMN_UUID.' = :uuid')
                ->andWhere(Employee::ALIAS.'.'.Employee::COLUMN_DELETED_AT.' IS NOT NULL')
                ->setParameter('uuid', $uuid)
                ->getQuery()
                ->getOneOrNullResult();

            if (null === $deletedEmployee) {
                throw new \Exception($this->translator->trans('employee.deleted.notExists', [':uuid' => $uuid], 'employees'), Response::HTTP_NOT_FOUND);
            }

            return $deletedEmployee;
        } finally {
            $filters->enable('soft_delete');
        }
    }

    public function getDeletedAddressByEmployeeByUUID(string $uuid): ?Address
    {
        $filters = $this->getEntityManager()->getFilters();
        $filters->disable('soft_delete');

        try {
            $qb = $this->getEntityManager()->createQueryBuilder();
            $deletedAddress = $qb->select(Address::ALIAS)
                ->from(Address::class, Address::ALIAS)
                ->join(Address::ALIAS.'.'.Address::RELATION_EMPLOYEE, Employee::ALIAS)
                ->where(Employee::ALIAS.'.'.Employee::COLUMN_UUID.' = :uuid')
                ->andWhere(Address::ALIAS.'.'.Address::COLUMN_DELETED_AT.' IS NOT NULL')
                ->setParameter('uuid', $uuid)
                ->getQuery()
                ->getOneOrNullResult();

            if (null === $deletedAddress) {
                throw new \Exception($this->translator->trans('employee.deleted.address.notExists', [':uuid' => $uuid], 'employees'), Response::HTTP_NOT_FOUND);
            }

            return $deletedAddress;
        } finally {
            $filters->enable('soft_delete');
        }
    }

    public function getDeletedContactsByEmployeeByUUID(string $uuid): Collection
    {
        $filters = $this->getEntityManager()->getFilters();
        $filters->disable('soft_delete');

        try {
            $qb = $this->getEntityManager()->createQueryBuilder();
            $deletedContacts = $qb->select(Contact::ALIAS)
                ->from(Contact::class, Contact::ALIAS)
                ->join(Contact::ALIAS.'.'.Contact::RELATION_EMPLOYEE, Employee::ALIAS)
                ->where(Employee::ALIAS.'. '.Employee::COLUMN_UUID.' = :uuid')
                ->andWhere(Contact::ALIAS.'.'.Contact::COLUMN_DELETED_AT.' IS NOT NULL')
                ->setParameter('uuid', $uuid)
                ->getQuery()
                ->getResult();

            if (empty($deletedContacts)) {
                throw new \Exception($this->translator->trans('company.deleted.contacts.notExists', [':uuid' => $uuid], 'companies'), Response::HTTP_NOT_FOUND);
            }

            return new ArrayCollection($deletedContacts);
        } finally {
            $filters->enable('soft_delete');
        }
    }

    public function getDeletedUserByEmployeeUUID(string $uuid): ?User
    {
        $filters = $this->getEntityManager()->getFilters();
        $filters->disable('soft_delete');

        try {
            $qb = $this->getEntityManager()->createQueryBuilder();
            $deletedUser = $qb->select(User::ALIAS)
                ->from(User::class, User::ALIAS)
                ->join(User::ALIAS.'.'.User::RELATION_EMPLOYEE, Employee::ALIAS)
                ->where(Employee::ALIAS.'. '.Employee::COLUMN_UUID.' = :uuid')
                ->andWhere(User::ALIAS.'.'.User::COLUMN_DELETED_AT.' IS NOT NULL')
                ->setParameter('uuid', $uuid)
                ->getQuery()
                ->getOneOrNullResult();

            if (null === $deletedUser) {
                throw new \Exception($this->translator->trans('employee.deleted.notExists', [':uuid' => $uuid], 'employees'), Response::HTTP_NOT_FOUND);
            }

            return $deletedUser;
        } finally {
            $filters->enable('soft_delete');
        }
    }

    public function getEmployeesPESELByEmails(array $emails): Collection
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select(Employee::ALIAS.'.'.Employee::COLUMN_PESEL, Contact::ALIAS.'.'.Contact::COLUMN_DATA.' AS email')
            ->from(Employee::class, Employee::ALIAS)
            ->join(Employee::ALIAS.'.'.Employee::RELATION_CONTACTS, Contact::ALIAS)
            ->where(Contact::ALIAS.'.'.Contact::COLUMN_DATA.' IN (:emails)')
            ->setParameter('emails', $emails);

        $results = $qb->getQuery()->getArrayResult();

        $accounts = $this->getEntityManager()->createQueryBuilder()
            ->select('e.pesel, u.email AS email')
            ->from(User::class, 'u')
            ->leftJoin('u.employee', 'e')
            ->where('u.email IN (:emails)')
            ->setParameter('emails', $emails)
            ->getQuery()
            ->getArrayResult();

        return new ArrayCollection(array_merge($results, $accounts));
    }

    public function getAvailableParentEmployeeOptions(
        string $companyUUID,
        ?string $employeeUUID = null,
        ?string $departmentUUID = null
    ): array {
        $conn = $this->getEntityManager()->getConnection();

        if ($employeeUUID !== null) {
            $sql = <<<SQL
WITH RECURSIVE employee_tree AS (
    SELECT uuid, 0 AS depth
    FROM employee
    WHERE uuid = :employeeUuid

    UNION ALL

    SELECT e.uuid, et.depth + 1
    FROM employee e
    INNER JOIN employee_tree et ON e.employee_uuid = et.uuid
    WHERE et.depth < 63
)
SELECT 
    e.uuid,
    TRIM(CONCAT(e.first_name, ' ', e.last_name)) AS "fullName",
    (SELECT d.name FROM department d WHERE d.uuid = e.department_uuid AND d.deleted_at IS NULL) AS departmentName
FROM employee e
WHERE e.company_uuid = :companyUuid
  AND e.active = TRUE
  AND e.deleted_at IS NULL
  AND e.uuid NOT IN (SELECT uuid FROM employee_tree)
SQL;

            $params = [
                'employeeUuid' => $employeeUUID,
                'companyUuid'  => $companyUUID,
            ];
        } else {
            $sql = <<<SQL
SELECT 
    e.uuid,
    TRIM(CONCAT(e.first_name, ' ', e.last_name)) AS "fullName",
    (SELECT d.name FROM department d WHERE d.uuid = e.department_uuid AND d.deleted_at IS NULL) AS departmentName
FROM employee e
WHERE e.company_uuid = :companyUuid
  AND e.active = TRUE
  AND e.deleted_at IS NULL
SQL;

            $params = [
                'companyUuid' => $companyUUID,
            ];
        }

        if ($departmentUUID !== null) {
            $sql .= "\nAND e.department_uuid = :departmentUuid";
            $params['departmentUuid'] = $departmentUUID;
        }

        $sql .= "\nORDER BY e.last_name, e.first_name";

        return $conn->executeQuery($sql, $params)->fetchAllAssociative();
    }

    public function isEmployeeBelongsToDepartmentCompany(string $departmentUUID, string $employeeUUID): bool
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $qb->select('e')
            ->from(Employee::class, 'e')
            ->join('e.department', 'd')
            ->where('e.uuid = :employeeUUID')
            ->andWhere('d.uuid = :departmentUUID')
            ->setParameters(new ArrayCollection([
                new Parameter('employeeUUID', $employeeUUID),
                new Parameter('departmentUUID', $departmentUUID),
            ]))
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        return $result !== null;
    }
}
