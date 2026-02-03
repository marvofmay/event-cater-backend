<?php

declare(strict_types=1);

namespace App\Module\Company\Application\DTO\User;

use App\Common\Validator\Constraints\NotBlank;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\File;

final class ChangeUserAvatarDTO
{
    #[NotBlank(message: [
        'text' => 'user.avatar.type.required',
        'domain' => 'users',
    ])]
    #[Choice(
        choices: ['custom', 'default'],
        message: 'user.avatar.type.invalid',
    )]
    public string $type = 'default' {
        get {
            return $this->type;
        }
    }

    #[Choice(
        choices: ['man', 'woman'],
        message: 'user.avatar.default.invalid',
    )]
    public ?string $defaultAvatar = null;

    #[File(
        maxSize: '2M',
        mimeTypes: ['image/jpeg', 'image/png', 'image/jpg'],
        maxSizeMessage: 'user.avatar.file.tooLarge',
        mimeTypesMessage: 'user.avatar.file.invalidType'
    )]
    public ?UploadedFile $uploadedFile = null {
        get {
            return $this->uploadedFile;
        }
    }
}
