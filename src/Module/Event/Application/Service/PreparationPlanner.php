<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Service;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Module\Event\Application\Port\EmployeeDirectory;
use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EquipmentRequirement;
use App\Module\Event\Domain\Entity\EventPreparationList;
use App\Module\Event\Domain\Entity\PreparationLine;
use App\Module\Event\Domain\Enum\PreparationStatus;
use App\Module\Event\Domain\Repository\PreparationRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class PreparationPlanner
{
    public function __construct(private PreparationRepository $lists, private EntityManagerInterface $em, private EmployeeDirectory $employees)
    {
    }

    public function get(CateringEvent $event): EventPreparationList
    {
        return $this->lists->getForEvent($event);
    }

    public function requireLine(EventPreparationList $list, string $uuid): PreparationLine
    {
        return $list->line($uuid) ?? throw new PlanningResourceNotFound('Preparation line not found for this event.');
    }

    public function equipmentChanged(CateringEvent $event, EquipmentRequirement $equipment, ?array $previous): void
    {
        $list = $this->get($event);
        $line = $list->find('equipment', $equipment->equipmentUuid());
        if (!$line) {
            $list->add(PreparationLine::equipment($list, $equipment));
            return;
        }
        if ($previous !== $equipment->toArray() || $line->calculationRule() !== null) {
            $line->useRule(null);
            $list->invalidatePlan();
        }
    }

    public function equipmentRemoved(CateringEvent $event, EquipmentRequirement $equipment): void
    {
        $list = $this->get($event);
        $line = $list->find('equipment', $equipment->equipmentUuid());
        if (!$line) {
            throw new PlanningResourceNotFound('Preparation equipment line not found.');
        }
        $list->remove($line);
        $this->em->remove($line);
    }

    public function contextChanged(CateringEvent $event, array $previous): void
    {
        $current = $event->toArray();
        foreach (['date', 'timezone', 'readyAt', 'startsAt', 'endsAt', 'location', 'guestCount'] as $field) {
            if ($previous[$field] !== $current[$field]) {
                $list = $this->get($event);
                $list->assertDeadline();
                if ($previous['guestCount'] !== $current['guestCount']) {
                    $this->recalculate($list, $current['guestCount']);
                }
                $list->invalidatePlan();
                return;
            }
        }
    }

    public function recalculate(EventPreparationList $list, ?int $guestCount): void
    {
        foreach ($list->lines() as $line) {
            $rule = $line->calculationRule();
            if ($rule === null || $rule->mode !== 'per_guest') {
                continue;
            }
            $quantity = $rule->calculate($guestCount, $line->unit());
            if ($line->equipmentRequirement() !== null) {
                $requirement = $line->equipmentRequirement();
                $requirement->changeQuantity(intdiv($quantity->milliunits(), 1000), $requirement->toArray()['notes']);
            } else {
                $line->setRequiredQuantity($quantity);
            }
        }
    }

    public function validateResponsibilities(EventPreparationList $list): void
    {
        $ids = [];
        if ($list->responsibleUuid() !== null) {
            $ids[] = $list->responsibleUuid();
        }
        foreach ($list->lines() as $line) {
            if ($line->responsibleUuid() !== null) {
                $ids[] = $line->responsibleUuid();
            }
        }
        $ids = array_unique($ids);
        sort($ids);
        foreach ($ids as $id) {
            $this->employees->requireActive($id);
        }
    }

    public function checkpoint(EventPreparationList $list): array
    {
        $data = $list->toArray();
        unset($data['lines']);
        return $data;
    }

    public function assertDispatched(CateringEvent $event): void
    {
        $list = $this->get($event);
        if (!$list->isEmpty() && $list->status() !== PreparationStatus::LOADED) {
            throw new BusinessRuleViolation('Confirm the catering load before starting the event.');
        }
    }
}
