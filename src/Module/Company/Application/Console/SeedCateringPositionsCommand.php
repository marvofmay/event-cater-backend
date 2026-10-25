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

#[AsCommand(name: 'app:company:seed-catering-positions', description: 'Add catering positions and link them to the existing five departments without duplicating positions.')]
final class SeedCateringPositionsCommand extends Command
{
    private const array POSITIONS = [
        'Kuchnia' => ['Szef kuchni', 'Zastępca szefa kuchni', 'Kucharz', 'Pomoc kuchenna', 'Cukiernik'],
        'Logistyka' => ['Kierownik logistyki', 'Koordynator transportu', 'Kierowca', 'Magazynier', 'Pracownik kompletacji i załadunku', 'Pracownik montażu wyposażenia'],
        'Czystość i higiena' => ['Koordynator czystości i higieny', 'Pracownik utrzymania czystości', 'Pracownik zmywalni'],
        'Zarząd' => ['Prezes / Właściciel', 'Dyrektor operacyjny', 'Kierownik cateringu', 'Specjalista ds. administracji', 'Specjalista ds. sprzedaży i obsługi klienta'],
        'Obsługa' => ['Kierownik obsługi wydarzeń', 'Koordynator wydarzenia', 'Kierownik sali', 'Kelner', 'Barman', 'Pracownik obsługi bufetu'],
    ];

    public function __construct(private readonly TenantContext $tenant, private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('tenant', InputArgument::REQUIRED)->addArgument('company-uuid', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->tenant->select($input->getArgument('tenant'));
        $departments = [];
        foreach (array_keys(self::POSITIONS) as $name) {
            $matches = $this->em->getRepository(Department::class)->findBy(['name' => $name, 'company' => $input->getArgument('company-uuid')]);
            if (count($matches) !== 1) {
                throw new \RuntimeException('Expected exactly one active department: '.$name);
            }
            $departments[$name] = $matches[0];
        }
        $this->em->wrapInTransaction(function () use ($departments, $output): void {
            foreach (self::POSITIONS as $department => $names) {
                foreach ($names as $name) {
                    $position = $this->em->getRepository(Position::class)->findOneBy(['name' => $name]);
                    if (!$position) {
                        $position = Position::create($name, 'Stanowisko w dziale: '.$department, true);
                        $this->em->persist($position);
                    } else {
                        $position->activate();
                    }
                    $position->addDepartment($departments[$department]);
                    $output->writeln($department.': '.$position->getName());
                }
            }
        });

        return Command::SUCCESS;
    }
}
