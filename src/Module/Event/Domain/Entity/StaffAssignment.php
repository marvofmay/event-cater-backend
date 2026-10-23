<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Domain\Enum\EventFunction;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'event_staff_assignment')]
#[ORM\UniqueConstraint(name: 'event_employee_unique', columns: ['event_uuid', 'employee_uuid'])]
#[ORM\Index(name: 'employee_schedule', columns: ['employee_uuid', 'starts_at', 'ends_at'])]
class StaffAssignment
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\ManyToOne(targetEntity: CateringEvent::class)]
    #[ORM\JoinColumn(name: 'event_uuid', referencedColumnName: 'uuid', nullable: false, onDelete: 'CASCADE')]
    private CateringEvent $event;
    #[ORM\Column(length: 36)]
    private string $employeeUuid;
    #[ORM\Column(length: 250)]
    private string $employeeName;
    #[ORM\Column(type: 'json')]
    private array $functions;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startsAt;
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $endsAt;
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes;

    public function __construct(CateringEvent $event, string $employeeUuid, string $employeeName, array $functions, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, ?string $notes)
    {
        $this->uuid = Uuid::uuid4()->toString();
        $this->event = $event;
        $this->employeeUuid = Uuid::fromString($employeeUuid)->toString();
        $this->update($employeeName, $functions, $startsAt, $endsAt, $notes);
    }

    /** @param list<EventFunction> $functions */
    public function update(string $employeeName, array $functions, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt, ?string $notes): void
    {
        $this->event->assertEditable();
        if ($startsAt->format('u') !== '000000' || $endsAt->format('u') !== '000000') {
            throw new BusinessRuleViolation('Assignment times require whole-second precision.');
        }
        if ($startsAt >= $endsAt || $functions === [] || count($functions) > count(EventFunction::cases())) {
            throw new BusinessRuleViolation('Staff assignment requires functions and startsAt < endsAt.');
        }
        $values = array_map(static fn (EventFunction $function) => $function->value, $functions);
        if (count($values) !== count(array_unique($values))) {
            throw new BusinessRuleViolation('Staff functions must be unique.');
        }
        if ($notes !== null && mb_strlen($notes) > 4000) {
            throw new BusinessRuleViolation('Staff notes exceed 4000 characters.');
        }
        $this->employeeName = $employeeName;
        $this->functions = $values;
        $utc = new \DateTimeZone('UTC');
        $this->startsAt = $startsAt->setTimezone($utc);
        $this->endsAt = $endsAt->setTimezone($utc);
        $this->notes = $notes;
    }

    public function toArray(): array
    {
        return ['uuid' => $this->uuid, 'employeeUuid' => $this->employeeUuid, 'employeeName' => $this->employeeName,
            'functions' => $this->functions, 'startsAt' => $this->startsAt->format(DATE_ATOM),
            'endsAt' => $this->endsAt->format(DATE_ATOM), 'notes' => $this->notes];
    }
}
