<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Aggregate\Employee\ValueObject;

final class LastName extends NameAbstract
{
    public function __construct(string $value)
    {
        if ('' === trim($value)) {
            $this->value = '';

            return;
        }

        parent::__construct(trim($value));
    }
}
