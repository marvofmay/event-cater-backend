<?php

declare(strict_types=1);

namespace App\Module\System\Application\Command\Sms;

use App\Common\Domain\Interface\CommandInterface;

final readonly class SendBrevoSmsCommand implements CommandInterface
{
    public function __construct(public string $id, public string $userUuid, public string $recipient, public string $content)
    {
    }
}
