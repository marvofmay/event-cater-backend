<?php

declare(strict_types=1);

namespace App\Module\Company\Application\DTO\Employee;

use App\Module\Company\Domain\Aggregate\ValueObject\Address;
use Symfony\Component\Validator\Constraints as Assert;

final class EmployeeAddressDTO
{
    public function __construct(
        #[Assert\Length(max: 255)]
        public ?string $street = null,
        #[Assert\Length(max: 255)]
        public ?string $postcode = null,
        #[Assert\Length(max: 255)]
        public ?string $city = null,
        #[Assert\Length(max: 255)]
        public ?string $country = null,
    ) {
    }

    public function toAddress(): Address
    {
        return new Address(trim($this->street ?? ''), trim($this->postcode ?? ''), trim($this->city ?? ''), trim($this->country ?? ''));
    }
}
