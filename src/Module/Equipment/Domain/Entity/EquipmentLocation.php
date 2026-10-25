<?php

declare(strict_types=1);

namespace App\Module\Equipment\Domain\Entity;

use App\Common\Domain\Exception\BusinessRuleViolation;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'equipment_location')]
class EquipmentLocation
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $uuid;
    #[ORM\Column(length: 200, unique: true)]
    private string $name;
    #[ORM\Version]
    #[ORM\Column(type: 'integer')]
    private int $version = 1;

    public function __construct(string $uuid, string $name)
    {
        $this->uuid = Uuid::fromString($uuid)->toString();
        $this->rename($name);
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 200) {
            throw new BusinessRuleViolation('Nazwa miejsca jest wymagana i może mieć maksymalnie 200 znaków.');
        }
        $this->name = $name;
    }

    public function uuid(): string
    {
        return $this->uuid;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): int
    {
        return $this->version;
    }
}
