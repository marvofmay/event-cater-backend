<?php

declare(strict_types=1);

namespace App\Module\Company\Application\DTO\Employee;

use App\Common\Domain\Interface\QueryDTOInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ParentEmployeeOptionsQueryDTO implements QueryDTOInterface
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $companyUUID = '';

    #[Assert\Uuid]
    public ?string $departmentUUID = null;
    #[Assert\Uuid]
    public ?string $employeeUUID = null;
}
