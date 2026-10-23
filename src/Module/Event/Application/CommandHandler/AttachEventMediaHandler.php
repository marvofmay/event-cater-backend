<?php

declare(strict_types=1);

namespace App\Module\Event\Application\CommandHandler;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Company\Domain\Entity\User;
use App\Module\Event\Application\Command\AttachEventMedia;
use App\Module\Event\Application\Service\EventEditor;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EventAttachment;
use App\Module\System\Domain\Entity\File;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class AttachEventMediaHandler
{
    public function __construct(private EventEditor $editor, private EntityManagerInterface $em)
    {
    }
    public function __invoke(AttachEventMedia $command): void
    {
        $this->editor->edit($command->uuid, $command->version, $command->actorUuid, 'media_attached', function (CateringEvent $event) use ($command): array {
            $user = $this->em->find(User::class, $command->actorUuid);
            if (!$user) {
                throw new PlanningResourceNotFound('Author not found.');
            }
            $file = File::create($command->fileName, $command->storedName, $command->extension, 'event_media', $user);
            $attachment = new EventAttachment($event, $file->getUUID()->toString(), $command->mimeType, $command->size, $command->actorUuid, $command->description);
            $this->em->persist($file);
            $this->em->persist($attachment);
            return ['fileUuid' => $file->getUUID()->toString(), 'name' => $command->fileName, 'mimeType' => $command->mimeType, 'description' => $command->description];
        }, media: true);
    }
}
