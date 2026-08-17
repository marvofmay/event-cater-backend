<?php

declare(strict_types=1);

namespace App\Module\Company\Application\ReadModel\Address;

use App\Module\Company\Domain\Entity\Address;

final readonly class AddressView
{
    public function __construct(
        public string $street,
        public string $postcode,
        public string $city,
        public string $country,
    ) {
    }

    public static function fromAddress(Address $address): self
    {
        return new self(
            street: $address->getStreet(),
            postcode: $address->getPostcode(),
            city: $address->getCity(),
            country: $address->getCountry(),
        );
    }
}
