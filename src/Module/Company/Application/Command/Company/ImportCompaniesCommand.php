<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Command\Company;

use App\Common\Domain\Interface\CommandInterface;
use App\Module\Company\Application\Command\AsynchronousImportCommandInterface;

final readonly class ImportCompaniesCommand implements CommandInterface, AsynchronousImportCommandInterface
{
    public function __construct(public string $importUUID, public string $loggedUserUUID)
    {
    }

    public function getImportUUID(): string
    {
        return $this->importUUID;
    }
}
