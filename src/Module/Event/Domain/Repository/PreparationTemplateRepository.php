<?php

declare(strict_types=1);

namespace App\Module\Event\Domain\Repository;

use App\Module\Event\Domain\Entity\PreparationTemplate;

interface PreparationTemplateRepository
{
    public function getForApply(string $uuid, int $version): PreparationTemplate;
    public function add(PreparationTemplate $item): void;
    public function getForUpdate(string $uuid, int $version): PreparationTemplate;
}
