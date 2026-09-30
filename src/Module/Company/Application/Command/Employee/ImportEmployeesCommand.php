<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Command\Employee;

use App\Common\Domain\Interface\CommandInterface;
use App\Module\Company\Application\Command\AsynchronousImportCommandInterface;

final readonly class ImportEmployeesCommand implements CommandInterface, AsynchronousImportCommandInterface
{
    public function __construct(public ?string $importUUID, public string $loggedUserUUID)
    {
    }

    public function getImportUUID(): string
    {
        if (null === $this->importUUID) {
            throw new \LogicException('An asynchronous import command requires an import UUID.');
        }

        return $this->importUUID;
    }
}
