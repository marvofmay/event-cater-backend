<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Module\Event\Application\Command\ChangePreparationTemplateActive;
use App\Module\Event\Application\Command\CreatePreparationTemplate;
use App\Module\Event\Application\Command\UpdatePreparationTemplate;
use App\Module\Event\Application\Service\PreparationTemplateResolver;
use App\Module\Event\Domain\Entity\PreparationTemplate;
use App\Module\Event\Domain\Repository\PreparationTemplateRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class PreparationTemplateCommandHandler
{
    public function __construct(private PreparationTemplateRepository $templates, private PreparationTemplateResolver $resolver)
    {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function create(CreatePreparationTemplate $command): void
    {
        $this->templates->add(PreparationTemplate::create($command->uuid, $command->name, $command->description, $this->resolver->resolve($command->lines)));
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function update(UpdatePreparationTemplate $command): void
    {
        $template = $this->templates->getForUpdate($command->uuid, $command->version);
        $template->replaceDefinition($command->name, $command->description, $this->resolver->resolve($command->lines));
    }
    #[AsMessageHandler(bus: 'command.bus')]
    public function active(ChangePreparationTemplateActive $command): void
    {
        $this->templates->getForUpdate($command->uuid, $command->version)->changeActive($command->active);
    }
}
