<?php

declare(strict_types=1);

namespace App\Module\Company\Application\CommandHandler\Employee;

use App\Common\Domain\Abstract\CommandHandlerAbstract;
use App\Common\Domain\Enum\FileExtensionEnum;
use App\Common\Domain\Enum\FileKindEnum;
use App\Common\Domain\Service\EventStore\EventStoreCreator;
use App\Common\Domain\Service\UploadFile\UploadFile;
use App\Common\Domain\Trait\HandleEventStoreTrait;
use App\Module\Company\Application\Command\Employee\ChangeEmployeeAvatarCommand;
use App\Module\Company\Domain\Aggregate\Employee\EmployeeAggregate;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Interface\Employee\EmployeeAggregateReaderInterface;
use App\Module\System\Domain\Entity\File;
use App\Module\System\Domain\Service\File\FileCreator;
use App\Module\System\Domain\ValueObject\UserUUID;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangeEmployeeAvatarCommandHandler extends CommandHandlerAbstract
{
    use HandleEventStoreTrait;

    public function __construct(
        private readonly Security $security,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FileCreator $fileCreator,
        private readonly EmployeeAggregateReaderInterface $employeeAggregateReaderRepository,
        protected readonly ParameterBagInterface $params,
        private readonly SerializerInterface $serializer,
        private readonly EventStoreCreator $eventStoreCreator,
        #[Autowire(service: 'event.bus')] private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(ChangeEmployeeAvatarCommand $command): void
    {
        $filePath = null;
        $user = $this->security->getUser();
        $loggedUserUUID = $user->getUuid()->toString();
        $employeeUUID = $user->getEmployee()->getUUID()->toString();

        if ($command->avatarType === 'custom') {
            $relativePath = sprintf('avatars/%s', $loggedUserUUID);
            $uploadFilePath = sprintf('%s/%s', $this->params->get('public_upload_path'), $relativePath);

            $uploadFileService = new UploadFile(
                $uploadFilePath,
                [FileExtensionEnum::JPG->value, FileExtensionEnum::JPEG->value, FileExtensionEnum::PNG->value]
            );
            $uploadFileService->uploadFile($command->uploadedFile);
            $storedFile = $uploadFileService->getUploadedFile();
            $filePath = $relativePath . '/' . $storedFile->getFilename();
            
            $fileRelativePath = $relativePath . '/' . $storedFile->getFilename();

            $fileEntity = File::create(
                fileName: $storedFile->getFilename(),
                filePath: $fileRelativePath,
                fileExtension: $storedFile->getExtension(),
                fileKind: FileKindEnum::USER_AVATAR_PROFILE->value,
                user: $user
            );

            $this->fileCreator->create($fileEntity);
        }

        $employeeAggregate = $this->employeeAggregateReaderRepository->getEmployeeAggregateByUUID(
            EmployeeUUID::fromString($employeeUUID)
        );

        $employeeAggregate->changeAvatar(
            avatarType: $command->avatarType,
            defaultAvatar: $command->defaultAvatar,
            avatarPath: $filePath,
            loggedUserUUID: UserUUID::fromString($loggedUserUUID)
        );

        $events = $employeeAggregate->pullEvents();
        foreach ($events as $event) {
            $this->handleEvent($event, EmployeeAggregate::class);
        }
    }
}
