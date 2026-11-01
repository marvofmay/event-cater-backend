<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'application_preference')]
class ApplicationPreference
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $owner;

    #[ORM\Id]
    #[ORM\Column(length: 32)]
    private string $resource;

    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column]
    private int $version;
}
