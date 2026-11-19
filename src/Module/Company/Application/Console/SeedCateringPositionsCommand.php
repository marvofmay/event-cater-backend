<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Console;

use App\Module\Company\Domain\Entity\Department;
use App\Module\Company\Domain\Entity\Position;
use App\Module\Saas\Infrastructure\TenantContext;
use App\Module\System\Application\Console\DefaultData\Data\PositionEnum;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(name: 'app:company:seed-catering-positions', description: 'Add the default catering positions, including Właściciel, and link them to matching departments.')]
final class SeedCateringPositionsCommand extends Command
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
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
        foreach ($this->departmentNames() as $name) {
            $department = $this->findDepartment($name, is_string($companyUuid) ? $companyUuid : null);
            if ($department instanceof Department) {
                $departments[$name] = $department;
                continue;
            }
            $output->writeln('Brak działu „'.$name.'”. Stanowiska z tej grupy zostaną dodane bez powiązania.');
        }

        $this->em->wrapInTransaction(function () use ($departments, $output): void {
            foreach (PositionEnum::cases() as $positionEnum) {
                $name = $this->translator->trans('position.defaultData.name.'.$positionEnum->value, [], 'positions');
                $description = $this->translator->trans('position.defaultData.description.'.$positionEnum->value, [], 'positions');
                $department = $positionEnum->department();
                $position = $this->findPosition($name);
                if (!$position instanceof Position) {
                    $position = Position::create($name, $description, true);
                    $this->em->persist($position);
                } else {
                    $position->activate();
                }
                if (isset($departments[$department])) {
                    $position->addDepartment($departments[$department]);
                }
                $output->writeln($department.': '.$position->getName());
            }
        });

        return Command::SUCCESS;
    }

    /** @return list<string> */
    private function departmentNames(): array
    {
        $names = [];
        foreach (PositionEnum::cases() as $position) {
            $names[$position->department()] = $position->department();
        }

        return array_values($names);
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
