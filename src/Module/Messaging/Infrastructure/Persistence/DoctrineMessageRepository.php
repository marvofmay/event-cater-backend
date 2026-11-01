<?php

declare(strict_types=1);

namespace App\Module\Messaging\Infrastructure\Persistence;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Module\Messaging\Domain\Entity\DirectMessage;
use App\Module\Messaging\Domain\Entity\MessageReceipt;
use App\Module\Messaging\Domain\Repository\MessageRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMessageRepository implements MessageRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(string $uuid): ?DirectMessage
    {
        return $this->em->find(DirectMessage::class, $uuid);
    }

    public function add(DirectMessage $message): void
    {
        $this->em->persist($message);
    }

    public function markRead(string $messageUuid, string $userUuid): void
    {
        $receipt = $this->em->find(MessageReceipt::class, ['message' => $messageUuid, 'userUuid' => $userUuid], LockMode::PESSIMISTIC_WRITE)
            ?? throw new PlanningResourceNotFound('Nie znaleziono wiadomości w Twojej skrzynce.');
        if ($receipt->isDeleted()) {
            throw new PlanningResourceNotFound('Nie znaleziono wiadomości w Twojej skrzynce.');
        }
        $receipt->markRead();
    }

    public function replyRecipient(string $messageUuid, string $userUuid): string
    {
        $sender = $this->em->getConnection()->fetchOne('SELECT message.sender_uuid FROM direct_message message JOIN direct_message_receipt receipt ON receipt.message_uuid = message.uuid WHERE message.uuid = ? AND receipt.user_uuid = ? AND receipt.deleted_at IS NULL AND message.sender_uuid <> ? FOR SHARE', [$messageUuid, $userUuid, $userUuid]);
        return $sender ?: throw new PlanningResourceNotFound('Nie znaleziono odebranej wiadomości, na którą można odpowiedzieć.');
    }

    public function hide(array $uuids, string $userUuid, string $folder): void
    {
        $uuids = array_values(array_unique($uuids));
        sort($uuids);
        if (count($uuids) < 1 || count($uuids) > 100 || !in_array($folder, ['inbox', 'sent'], true)) {
            throw new \App\Common\Domain\Exception\BusinessRuleViolation('Wybierz skrzynkę i od 1 do 100 wiadomości.');
        }
        $db = $this->em->getConnection();
        $table = $folder === 'inbox' ? 'direct_message_receipt' : 'direct_message';
        $id = $folder === 'inbox' ? 'message_uuid' : 'uuid';
        $owner = $folder === 'inbox' ? 'user_uuid' : 'sender_uuid';
        $deleted = $folder === 'inbox' ? 'deleted_at' : 'sender_deleted_at';
        $params = [$userUuid, $uuids];
        $types = [\Doctrine\DBAL\ParameterType::STRING, \Doctrine\DBAL\ArrayParameterType::STRING];
        $rows = $db->fetchFirstColumn('SELECT '.$id.' FROM '.$table.' WHERE '.$owner.' = ? AND '.$id.' IN (?) ORDER BY '.$id.' FOR UPDATE', $params, $types);
        if (count($rows) !== count($uuids)) {
            throw new PlanningResourceNotFound('Co najmniej jedna wiadomość nie należy do wybranej skrzynki.');
        }
        $db->executeStatement('UPDATE '.$table.' SET '.$deleted.' = COALESCE('.$deleted.', UTC_TIMESTAMP()) WHERE '.$owner.' = ? AND '.$id.' IN (?)', $params, $types);
    }
}
