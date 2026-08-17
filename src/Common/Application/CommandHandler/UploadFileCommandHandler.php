<?php

declare(strict_types=1);

namespace App\Common\Application\CommandHandler;

use App\Common\Application\Command\UploadFileCommand;
use App\Common\Domain\Enum\FileExtensionEnum;
use App\Common\Domain\Service\UploadFile\UploadFile;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class UploadFileCommandHandler
{
    public function __invoke(UploadFileCommand $command): void
    {
        $uploadFileService = new UploadFile($command->uploadFilePath, [FileExtensionEnum::XLSX->value], $command->uploadFileName);
        $uploadFileService->uploadFile($command->file);
    }
}
