<?php

declare(strict_types=1);

namespace App\Module\Equipment\Application\Console;

use App\Module\Equipment\Domain\Entity\EquipmentLocation;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsCommand(name: 'app:initialize-equipment-locations')]
#[AutoconfigureTag('app.command.initialize-system-default-data', ['priority' => -220])]
final class InitializeEquipmentLocations extends Command
{
    private const array NAMES = [
        'pod schodami po stronie prawej (zmywaka)',
        'pod schodami po stronie lewej (toalet)',
        'za barem',
        'w biurze',
        'w magazynie po stronie stołów bilardowych',
        'na zmywaku',
        'w przejściu do kuchni',
        'w szafie za drzwiami "western"',
        'w magazynie po prawej stronie za drzwiami "western"',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repository = $this->em->getRepository(EquipmentLocation::class);
        foreach (self::NAMES as $name) {
            if ($repository->findOneBy(['name' => $name]) === null) {
                $this->em->persist(new EquipmentLocation(Uuid::uuid4()->toString(), $name));
            }
        }
        $this->em->flush();
        $output->writeln('Uzupełniono miejsca przechowywania sprzętu.');
        return Command::SUCCESS;
    }
}
