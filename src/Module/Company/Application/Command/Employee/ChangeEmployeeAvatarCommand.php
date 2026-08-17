<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Command\Employee;

use App\Common\Domain\Interface\CommandInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final readonly class ChangeEmployeeAvatarCommand implements CommandInterface
{
    public function __construct(public string $avatarType, public ?string $defaultAvatar, public ?UploadedFile $uploadedFile)
    {
    }
}
