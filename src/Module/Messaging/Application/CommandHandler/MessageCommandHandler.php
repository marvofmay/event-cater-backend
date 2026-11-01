<?php

declare(strict_types=1);

namespace App\Module\Messaging\Application\CommandHandler;

use App\Common\Domain\Exception\PlanningConflict;
use App\Module\Messaging\Application\Command\MarkMessageRead;
use App\Module\Messaging\Application\Command\SendMessage;
use App\Module\Messaging\Application\Port\RecipientDirectory;
use App\Module\Messaging\Domain\Entity\DirectMessage;
use App\Module\Messaging\Domain\Repository\MessageRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class MessageCommandHandler
{
    public function __construct(private MessageRepository $messages, private RecipientDirectory $recipients)
    {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function send(SendMessage $command): void
    {
        $existing = $this->messages->find($command->uuid);
        if ($existing !== null) {
            if (!$existing->matches($command->senderUuid, $command->title, $command->content, $command->recipientUuids, $command->replyToUuid)) {
                throw new PlanningConflict('Identyfikator wysyłki został już użyty dla innej wiadomości.');
            }
            return;
        }
        $recipientUuids = array_values(array_unique($command->recipientUuids));
        if ($command->replyToUuid !== null && $recipientUuids !== [$this->messages->replyRecipient($command->replyToUuid, $command->senderUuid)]) {
            throw new \App\Common\Domain\Exception\BusinessRuleViolation('Odpowiedź może być skierowana tylko do nadawcy odebranej wiadomości.');
        }
        $recipients = $this->recipients->resolve($recipientUuids);
        $this->messages->add(new DirectMessage($command->uuid, $command->senderUuid, $this->recipients->senderName($command->senderUuid), $command->title, $command->content, $recipients, $command->replyToUuid));
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function read(MarkMessageRead $command): void
    {
        $this->messages->markRead($command->uuid, $command->userUuid);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function delete(\App\Module\Messaging\Application\Command\DeleteMessages $command): void
    {
        $this->messages->hide($command->uuids, $command->userUuid, $command->folder);
    }
}
