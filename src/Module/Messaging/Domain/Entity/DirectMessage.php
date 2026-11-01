<?php

declare(strict_types=1);

namespace App\Module\Messaging\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'direct_message')]
#[ORM\Index(name: 'direct_message_sender_date', columns: ['sender_uuid', 'sender_deleted_at', 'created_at'])]
class DirectMessage
{
    #[ORM\Id] #[ORM\Column(length: 36)] private string $uuid;
    #[ORM\Column(length: 36)] private string $senderUuid;
    #[ORM\Column(length: 300)] private string $senderName;
    #[ORM\Column(length: 200)] private string $title;
    #[ORM\Column(type: 'text')] private string $content;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $createdAt;
    #[ORM\Column(length: 36, nullable: true)] private ?string $replyToUuid = null;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $senderDeletedAt = null;
    #[ORM\OneToMany(targetEntity: MessageReceipt::class, mappedBy: 'message', cascade: ['persist', 'remove'])]
    private Collection $receipts;

    public function __construct(string $uuid, string $senderUuid, string $senderName, string $title, string $content, array $recipients, ?string $replyToUuid = null)
    {
        $title = trim($title);
        $content = trim($content);
        if ($title === '' || mb_strlen($title) > 200 || $content === '' || mb_strlen($content) > 10000) {
            throw new BusinessRuleViolation('Wiadomość wymaga tytułu do 200 znaków i treści do 10 000 znaków.');
        }
        if (count($recipients) < 1 || count($recipients) > 100) {
            throw new BusinessRuleViolation('Wybierz od 1 do 100 odbiorców.');
        }
        $this->uuid = Uuid::fromString($uuid)->toString();
        $this->senderUuid = Uuid::fromString($senderUuid)->toString();
        $this->senderName = $senderName;
        $this->replyToUuid = $replyToUuid === null ? null : Uuid::fromString($replyToUuid)->toString();
        $this->title = $title;
        $this->content = $content;
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->receipts = new ArrayCollection();
        $seen = [];
        foreach ($recipients as $recipient) {
            if (!isset($seen[$recipient['uuid']])) {
                $this->receipts->add(new MessageReceipt($this, $recipient['uuid'], $recipient['name']));
                $seen[$recipient['uuid']] = true;
            }
        }
    }

    public function matches(string $senderUuid, string $title, string $content, array $recipientUuids, ?string $replyToUuid = null): bool
    {
        $stored = $this->receipts->map(static fn (MessageReceipt $receipt) => $receipt->userUuid())->toArray();
        $incoming = array_values(array_unique($recipientUuids));
        sort($stored);
        sort($incoming);
        return $this->senderUuid === $senderUuid && $this->replyToUuid === $replyToUuid && $this->title === trim($title) && $this->content === trim($content) && $stored === $incoming;
    }
}
