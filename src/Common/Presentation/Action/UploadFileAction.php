<?php

declare(strict_types=1);

namespace App\Common\Presentation\Action;

use App\Common\Application\Command\UploadFileCommand;
use App\Common\Application\DTO\UploadFileDTO;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

// Todo:: Check if is unnecessary class - remove
readonly class UploadFileAction
{
    public function __construct(#[Autowire(service: 'command.bus')] private MessageBusInterface $commandBus)
    {
    }

    public function execute(UploadFileDTO $uploadFileDTO): void
    {
        $this->commandBus->dispatch(new UploadFileCommand($uploadFileDTO->file, $uploadFileDTO->uploadFilePath, $uploadFileDTO->uploadFileName));
    }
}
