<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Repository;

use App\Module\Event\Domain\Entity\CateringEvent;
use App\Module\Event\Domain\Entity\EventPreparationList;

interface PreparationRepository
{
    /** Called inside command.bus transaction, after locking the event/version. */
    public function getForEvent(CateringEvent $event): EventPreparationList;
}
