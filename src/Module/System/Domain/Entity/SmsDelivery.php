<?php

declare(strict_types=1);

namespace App\Module\System\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sms_delivery')]
#[ORM\Index(name: 'sms_period_status', columns: ['period', 'status'])]
class SmsDelivery
{
    #[ORM\Id]
    #[ORM\Column(length: 36)]
    private string $id;

    #[ORM\Column(length: 36)]
    private string $userUuid;

    #[ORM\Column(length: 7)]
    private string $period;

    #[ORM\Column]
    private int $units;

    #[ORM\Column(length: 16)]
    private string $status;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $providerId;
}
