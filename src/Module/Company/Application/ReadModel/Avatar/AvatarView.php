<?php

declare(strict_types=1);

namespace App\Module\Company\Application\ReadModel\Avatar;

final readonly class AvatarView
{
    public function __construct(
        public string $avatarType,
        public ?string $defaultAvatar,
        public ?string $avatarPath
    ) {
    }
}