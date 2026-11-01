<?php

declare(strict_types=1);

namespace App\Module\Messaging\Infrastructure\Query;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Messaging\Application\Port\RecipientDirectory;
use App\Module\Messaging\Application\Query\GetMessage;
use App\Module\Messaging\Application\Query\ListMessages;
use App\Module\Messaging\Application\Query\SearchMessageRecipients;
use App\Module\Messaging\Application\Query\UnreadMessageCount;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class MessageQueryHandler
{
    public function __construct(private Connection $db, private RecipientDirectory $recipients)
    {
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function list(ListMessages $query): array
    {
        if (!in_array($query->folder, ['inbox', 'sent'], true) || ($query->folder === 'sent' && $query->unread)) {
            throw new BusinessRuleViolation('Nieprawidłowa skrzynka wiadomości.');
        }
        $params = [$query->userUuid];
        $source = $query->folder === 'inbox'
            ? ' FROM direct_message message INNER JOIN direct_message_receipt receipt ON receipt.message_uuid = message.uuid WHERE receipt.user_uuid = ? AND receipt.deleted_at IS NULL'
            : ' FROM direct_message message WHERE message.sender_uuid = ? AND message.sender_deleted_at IS NULL';
        if ($query->unread) {
            $source .= ' AND receipt.read_at IS NULL';
        }
        if ($query->search !== '') {
            $source .= " AND (message.title LIKE ? ESCAPE '!' OR message.content LIKE ? ESCAPE '!')";
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query->search).'%';
            array_push($params, $pattern, $pattern);
        }
        $total = (int) $this->db->fetchOne('SELECT COUNT(*)'.$source, $params);
        $read = $query->folder === 'inbox' ? 'receipt.read_at' : 'NULL';
        $sort = ['createdAt' => 'message.created_at', 'title' => 'message.title', 'senderName' => 'message.sender_name'];
        if (!isset($sort[$query->sortBy]) || !in_array($query->sortDirection, ['asc', 'desc'], true)) {
            throw new BusinessRuleViolation('Nieprawidłowe sortowanie wiadomości.');
        }
        $rows = $this->db->fetchAllAssociative('SELECT message.uuid, message.title, message.sender_uuid, message.sender_name, message.reply_to_uuid, message.created_at, SUBSTRING(message.content, 1, 160) AS preview, '.$read.' AS read_at, (SELECT COUNT(*) FROM direct_message_receipt recipients WHERE recipients.message_uuid = message.uuid) AS recipient_count'.$source.' ORDER BY '.$sort[$query->sortBy].' '.$query->sortDirection.', message.uuid '.$query->sortDirection.' LIMIT '.$query->page->limit.' OFFSET '.$query->page->offset(), $params);
        return $query->page->result(array_map(self::project(...), $rows), $total);
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function get(GetMessage $query): array
    {
        $row = $this->db->fetchAssociative('SELECT message.*, receipt.read_at FROM direct_message message LEFT JOIN direct_message_receipt receipt ON receipt.message_uuid = message.uuid AND receipt.user_uuid = ? AND receipt.deleted_at IS NULL WHERE message.uuid = ? AND ((message.sender_uuid = ? AND message.sender_deleted_at IS NULL) OR receipt.user_uuid IS NOT NULL)', [$query->userUuid, $query->uuid, $query->userUuid])
            ?: throw new PlanningResourceNotFound('Nie znaleziono wiadomości.');
        $result = self::project($row);
        $result['content'] = $row['content'];
        $result['isSender'] = $row['sender_uuid'] === $query->userUuid;
        $result['isRecipient'] = (bool) $this->db->fetchOne('SELECT COUNT(*) FROM direct_message_receipt WHERE message_uuid = ? AND user_uuid = ? AND deleted_at IS NULL', [$query->uuid, $query->userUuid]);
        $recipients = $this->db->fetchAllAssociative('SELECT user_uuid, recipient_name, read_at FROM direct_message_receipt WHERE message_uuid = ?'.($result['isSender'] ? '' : ' AND user_uuid = ?').' ORDER BY recipient_name, user_uuid', $result['isSender'] ? [$query->uuid] : [$query->uuid, $query->userUuid]);
        $result['recipients'] = array_map(static fn (array $recipient) => ['uuid' => $recipient['user_uuid'], 'name' => $recipient['recipient_name'], 'readAt' => self::date($recipient['read_at'])], $recipients);
        return $result;
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function unread(UnreadMessageCount $query): array
    {
        return ['unreadCount' => (int) $this->db->fetchOne('SELECT COUNT(*) FROM direct_message_receipt WHERE user_uuid = ? AND read_at IS NULL AND deleted_at IS NULL', [$query->userUuid])];
    }

    #[AsMessageHandler(bus: 'query.bus')]
    public function recipients(SearchMessageRecipients $query): array
    {
        return ['items' => $this->recipients->search($query->search)];
    }

    private static function project(array $row): array
    {
        return ['uuid' => $row['uuid'], 'title' => $row['title'], 'senderUuid' => $row['sender_uuid'], 'senderName' => $row['sender_name'], 'replyToUuid' => $row['reply_to_uuid'], 'createdAt' => self::date($row['created_at']), 'readAt' => self::date($row['read_at']), 'preview' => $row['preview'] ?? '', 'recipientCount' => isset($row['recipient_count']) ? (int) $row['recipient_count'] : null];
    }

    private static function date(?string $date): ?string
    {
        return $date === null ? null : (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->format(DATE_ATOM);
    }
}
