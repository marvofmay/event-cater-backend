<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Console;

use App\Module\Company\Domain\Entity\Department;
use App\Module\Company\Domain\Entity\Position;
use App\Module\Saas\Infrastructure\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:company:seed-catering-positions', description: 'Add catering positions, including Właściciel, and link them to matching departments.')]
final class SeedCateringPositionsCommand extends Command
{
    private const array POSITIONS = [
        'Kuchnia' => ['Szef kuchni', 'Zastępca szefa kuchni', 'Kucharz', 'Pomoc kuchenna', 'Cukiernik'],
        'Logistyka' => ['Kierownik logistyki', 'Koordynator transportu', 'Kierowca', 'Magazynier', 'Pracownik kompletacji i załadunku', 'Pracownik montażu wyposażenia'],
        'Czystość i higiena' => ['Koordynator czystości i higieny', 'Pracownik utrzymania czystości', 'Pracownik zmywalni'],
        'Zarząd' => ['Właściciel', 'Dyrektor operacyjny', 'Kierownik cateringu', 'Specjalista ds. administracji', 'Specjalista ds. sprzedaży i obsługi klienta'],
        'Obsługa' => ['Kierownik obsługi wydarzeń', 'Koordynator wydarzenia', 'Kierownik sali', 'Kelner', 'Barman', 'Pracownik obsługi bufetu'],
    ];

    public function __construct(private readonly TenantContext $tenant, private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant', InputArgument::REQUIRED)
            ->addArgument('company-uuid', InputArgument::OPTIONAL, 'Limit department links to one company');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->tenant->select($input->getArgument('tenant'));
        $companyUuid = $input->getArgument('company-uuid');
        $departments = [];
        foreach (array_keys(self::POSITIONS) as $name) {
            $department = $this->findDepartment($name, is_string($companyUuid) ? $companyUuid : null);
            if ($department instanceof Department) {
                $departments[$name] = $department;
                continue;
            }
            $output->writeln('Brak działu „'.$name.'”. Stanowiska z tej grupy zostaną dodane bez powiązania.');
        }

        $this->em->wrapInTransaction(function () use ($departments, $output): void {
            foreach (self::POSITIONS as $department => $names) {
                foreach ($names as $name) {
                    $position = $this->findPosition($name);
                    if (!$position instanceof Position) {
                        $position = Position::create($name, 'Stanowisko w dziale: '.$department, true);
                        $this->em->persist($position);
                    } else {
                        $position->activate();
                    }
                    if (isset($departments[$department])) {
                        $position->addDepartment($departments[$department]);
                    }
                    $output->writeln($department.': '.$position->getName());
                }
            }
        });

        return Command::SUCCESS;
    }

    private function findDepartment(string $name, ?string $companyUuid): ?Department
    {
        $query = $this->em->createQueryBuilder()
            ->select('department')
            ->from(Department::class, 'department')
            ->where('LOWER(department.name) = :name')
            ->setParameter('name', mb_strtolower($name));
        if ($companyUuid !== null && $companyUuid !== '') {
            $query->andWhere('department.company = :company')->setParameter('company', $companyUuid);
        }
        $matches = $query->getQuery()->getResult();
        if (count($matches) > 1) {
            throw new \RuntimeException('Więcej niż jeden dział „'.$name.'”. Podaj UUID firmy.');
        }

        return $matches[0] ?? null;
    }

    private function findPosition(string $name): ?Position
    {
        return $this->em->createQueryBuilder()
            ->select('position')
            ->from(Position::class, 'position')
            ->where('LOWER(position.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
