<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Command\Department;

use App\Common\Domain\Interface\CommandInterface;
use App\Module\Company\Application\Command\AsynchronousImportCommandInterface;

final readonly class ImportDepartmentsCommand implements CommandInterface, AsynchronousImportCommandInterface
{
    public function __construct(public string $importUUID, public string $loggedUserUUID)
    {
    }

    public function getImportUUID(): string
    {
        return $this->importUUID;
    }
}
