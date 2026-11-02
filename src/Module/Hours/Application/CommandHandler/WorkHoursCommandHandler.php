<?php

declare(strict_types=1);

namespace App\Module\Hours\Application\CommandHandler;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Hours\Application\Command\DeleteWorkHours;
use App\Module\Hours\Application\Command\ReportWorkHours;
use App\Module\Hours\Application\Command\UpdateWorkHours;
use App\Module\Hours\Domain\Entity\WorkHoursEntry;
use App\Module\Hours\Domain\Repository\WorkHoursRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final readonly class WorkHoursCommandHandler
{
    public function __construct(private WorkHoursRepository $entries)
    {
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function report(ReportWorkHours $command): void
    {
        $existing = $this->entries->find($command->uuid);
        if ($existing !== null) {
            if ($existing->employeeUuid() !== $command->employeeUuid) {
                throw new BusinessRuleViolation('Identyfikator wpisu godzin został już użyty.');
            }
            return;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $command->date, new \DateTimeZone('UTC'));
        if ($date === false) {
            throw new BusinessRuleViolation('Nieprawidłowa data pracy.');
        }
        $today = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Warsaw')))->format('Y-m-d');
        if ($command->date > $today) {
            throw new BusinessRuleViolation('Nie można raportować godzin z przyszłości.');
        }
        $this->entries->add(new WorkHoursEntry(
            $command->uuid,
            $command->employeeUuid,
            $command->employeeName,
            $date,
            $command->minutes,
            $command->comment,
            $command->kind,
            $command->userUuid,
            $command->eventUuid,
        ));
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function update(UpdateWorkHours $command): void
    {
        $entry = $this->entries->find($command->uuid);
        if ($entry === null || $entry->employeeUuid() !== $command->employeeUuid) {
            throw new PlanningResourceNotFound('Nie znaleziono wpisu godzin.');
        }
        $entry->update($command->minutes, $command->comment);
        $this->entries->add($entry);
    }

    #[AsMessageHandler(bus: 'command.bus')]
    public function delete(DeleteWorkHours $command): void
    {
        $entry = $this->entries->find($command->uuid);
        if ($entry === null || $entry->employeeUuid() !== $command->employeeUuid) {
            throw new PlanningResourceNotFound('Nie znaleziono wpisu godzin.');
        }
        $this->entries->remove($entry);
    }
}
