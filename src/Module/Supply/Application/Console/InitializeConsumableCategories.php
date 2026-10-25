<?php

declare(strict_types=1);

namespace App\Module\Supply\Application\Console;

use App\Module\Supply\Domain\Entity\ConsumableCategory;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AsCommand(name: 'app:initialize-consumable-categories')]
#[AutoconfigureTag('app.command.initialize-system-default-data', ['priority' => -210])]
final class InitializeConsumableCategories extends Command
{
    private const array NAMES = [
        'Napoje i dodatki',
        'Produkty spożywcze',
        'Naczynia i sztućce jednorazowe',
        'Opakowania',
        'Higiena i sprzątanie',
        'Materiały pomocnicze',
        'Inne',
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repository = $this->em->getRepository(ConsumableCategory::class);
        foreach (self::NAMES as $name) {
            if ($repository->findOneBy(['name' => $name]) === null) {
                $this->em->persist(new ConsumableCategory(Uuid::uuid4()->toString(), $name));
            }
        }
        $this->em->flush();
        $output->writeln('Uzupełniono kategorie produktów i materiałów zużywalnych.');
        return Command::SUCCESS;
    }
}
