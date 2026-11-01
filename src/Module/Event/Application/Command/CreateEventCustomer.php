<?php

declare(strict_types=1);

namespace App\Module\Event\Application\Command;

final readonly class CreateEventCustomer
{
    public function __construct(public string $uuid, public string $name, public ?string $nip, public ?string $regon, public ?string $krs, public ?string $address, public ?string $phone, public ?string $email, public ?string $city = null, public ?string $street = null, public ?string $postalCode = null, public ?string $country = 'Polska')
    {
    }
}
