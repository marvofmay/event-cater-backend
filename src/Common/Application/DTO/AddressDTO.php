<?php

declare(strict_types=1);

namespace App\Common\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class AddressDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 250, maxMessage: 'address.street.max')]
        public ?string $street = null,
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 10, maxMessage: 'address.postcode.max')]
        public ?string $postcode = null,
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 50, maxMessage: 'address.city.max')]
        public ?string $city = null,
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 255, maxMessage: 'address.country.max')]
        public ?string $country = null,
        #[Assert\Type(
            type: 'bool',
        )]
        public bool $active = true,
    ) {
    }
}
