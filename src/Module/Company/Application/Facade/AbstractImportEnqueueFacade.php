<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Facade;

use App\Common\Application\Command\UploadFileCommand;
use App\Common\Application\DTO\UploadFileDTO;
use App\Common\Domain\Enum\FileExtensionEnum;
use App\Common\Domain\Enum\FileKindEnum;
use App\Common\Domain\Service\UploadFile\UploadFile;
use App\Module\System\Application\Command\File\CreateFileCommand;
use App\Module\System\Application\Command\Import\CreateImportCommand;
use App\Module\System\Application\Query\File\GetFileByNamePathAndKindQuery;
use App\Module\System\Application\Query\Import\GetImportByFileQuery;
use App\Module\System\Domain\Entity\File;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Enum\Import\ImportStatusEnum;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class AbstractImportEnqueueFacade
{
    public function __construct(
        protected ValidatorInterface $validator,
        protected Security $security,
        protected ParameterBagInterface $params,
        #[Autowire(service: 'command.bus')] protected MessageBusInterface $commandBus,
        #[Autowire(service: 'query.bus')] protected MessageBusInterface $queryBus,
    ) {
    }

    protected function handle(UploadedFile $file, string $folder, ImportKindEnum $importKind, callable $importCommand): void
    {
        $user = $this->security->getUser();
        $uploadFilePath = sprintf('%s/%s', $this->params->get('upload_import_file_path'), $folder);

        $fileName = UploadFile::generateUniqueFileName(FileExtensionEnum::XLSX->value);
        $uploadFileDTO = new UploadFileDTO($file, $uploadFilePath, $fileName);

        $errors = $this->validator->validate($uploadFileDTO);
        if (count($errors) > 0) {
            throw new \DomainException('Invalid file');
        }

        $this->commandBus->dispatch(new UploadFileCommand(
            $uploadFileDTO->file,
            $uploadFileDTO->uploadFilePath,
            $uploadFileDTO->uploadFileName
        ));

        $this->commandBus->dispatch(new CreateFileCommand(
            File::create(
                fileName: $fileName,
                filePath: $uploadFilePath,
                fileExtension: FileExtensionEnum::XLSX->value,
                fileKind: FileKindEnum::IMPORT_XLSX->value,
                user: $user
            )
        ));

        $fileEntity = $this->queryBus
            ->dispatch(new GetFileByNamePathAndKindQuery($fileName, $uploadFilePath, FileKindEnum::IMPORT_XLSX))
            ->last(HandledStamp::class)
            ->getResult();

        $this->commandBus->dispatch(new CreateImportCommand(
            kindEnum: $importKind,
            statusEnum: ImportStatusEnum::PENDING,
            file: $fileEntity,
            user: $user,
        ));

        $import = $this->queryBus
            ->dispatch(new GetImportByFileQuery($fileEntity))
            ->last(HandledStamp::class)
            ->getResult();

        $this->commandBus->dispatch(
            $importCommand(
                $import->getUUID()->toString(),
                $user->getUUID()->toString()
            )
        );
    }
}
