<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Event\Application\Command\RemoveEventMedia;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EventAttachment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class RemoveEventMediaHandler
{
    public function __construct(private EventEditor $editor, private EntityManagerInterface $em)
    {
    }
    public function __invoke(RemoveEventMedia $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'media_removed', function (CateringEvent $event) use ($command): array {
            $attachment = $this->em->getRepository(EventAttachment::class)->findOneBy(['uuid' => $command->attachmentUuid, 'event' => $event]);
            if (!$attachment) {
                throw new PlanningResourceNotFound('Attachment not found.');
            }
            $this->em->remove($attachment);
            return $attachment->toArray();
        }, media: true);
    }
}
