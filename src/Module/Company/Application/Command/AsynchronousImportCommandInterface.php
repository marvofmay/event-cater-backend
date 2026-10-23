<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Command;

interface AsynchronousImportCommandInterface
{
    public function getImportUUID(): string;
}
