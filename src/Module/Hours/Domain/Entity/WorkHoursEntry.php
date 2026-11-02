<?php

declare(strict_types=1);

namespace App\Module\Hours\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'work_hours_entry')]
#[ORM\Index(name: 'work_hours_employee_date', columns: ['employee_uuid', 'work_date'])]
#[ORM\Index(name: 'work_hours_date', columns: ['work_date'])]
#[ORM\Index(name: 'work_hours_event', columns: ['event_uuid'])]
class WorkHoursEntry
{
    public const KIND_MANUAL = 'manual';
    public const KIND_ADJUSTMENT = 'adjustment';

    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\Column(length: 36)]
    private string $employeeUuid;

    #[ORM\Column(length: 250)]
    private string $employeeName;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $workDate;

    #[ORM\Column(type: 'integer')]
    private int $minutes;

    #[ORM\Column(type: 'text')]
    private string $comment;

    #[ORM\Column(length: 32)]
    private string $kind;

    #[ORM\Column(length: 36, nullable: true)]
    private ?string $eventUuid;

    #[ORM\Column(length: 36)]
    private string $createdByUserUuid;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $uuid,
        string $employeeUuid,
        string $employeeName,
        \DateTimeImmutable $workDate,
        int $minutes,
        string $comment,
        string $kind,
        string $createdByUserUuid,
        ?string $eventUuid = null,
    ) {
        $this->uuid = Uuid::fromString($uuid)->toString();
        $this->employeeUuid = Uuid::fromString($employeeUuid)->toString();
        $this->employeeName = trim($employeeName);
        $this->createdByUserUuid = Uuid::fromString($createdByUserUuid)->toString();
        $this->eventUuid = $eventUuid === null ? null : Uuid::fromString($eventUuid)->toString();
        $this->createdAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->updatedAt = $this->createdAt;
        $this->apply($workDate, $minutes, $comment, $kind);
    }

    public function update(int $minutes, string $comment): void
    {
        $this->apply($this->workDate, $minutes, $comment, $this->kind);
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    private function apply(\DateTimeImmutable $workDate, int $minutes, string $comment, string $kind): void
    {
        $comment = trim($comment);
        if ($minutes < 1 || $minutes > 24 * 60) {
            throw new BusinessRuleViolation('Czas pracy musi być w zakresie 1–1440 minut.');
        }
        if ($comment === '' || mb_strlen($comment) < 5 || mb_strlen($comment) > 4000) {
            throw new BusinessRuleViolation('Komentarz jest obowiązkowy (5–4000 znaków) i wyjaśnia czas pracy.');
        }
        if (!in_array($kind, [self::KIND_MANUAL, self::KIND_ADJUSTMENT], true)) {
            throw new BusinessRuleViolation('Nieprawidłowy rodzaj wpisu godzin.');
        }
        $this->workDate = $workDate->setTime(0, 0);
        $this->minutes = $minutes;
        $this->comment = $comment;
        $this->kind = $kind;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function employeeUuid(): string
    {
        return $this->employeeUuid;
    }

    public function toArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'employeeUuid' => $this->employeeUuid,
            'employeeName' => $this->employeeName,
            'date' => $this->workDate->format('Y-m-d'),
            'minutes' => $this->minutes,
            'comment' => $this->comment,
            'kind' => $this->kind,
            'eventUuid' => $this->eventUuid,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}
