<?php

declare(strict_types=1);

namespace App\Module\Company\Application\Facade;

use App\Common\Application\Command\UploadFileCommand;
use App\Common\Application\DTO\UploadFileDTO;
use App\Common\Domain\Enum\FileExtensionEnum;
use App\Common\Domain\Enum\FileKindEnum;
use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Domain\Service\UploadFile\UploadFile;
use App\Module\System\Application\Command\File\CreateFileCommand;
use App\Module\System\Application\Command\Import\CreateImportCommand;
use App\Module\System\Application\Event\LogFileEvent;
use App\Module\System\Application\Query\File\GetFileByNamePathAndKindQuery;
use App\Module\System\Application\Query\Import\GetImportByFileQuery;
use App\Module\System\Application\Query\ImportLog\GetImportLogsByImportQuery;
use App\Module\System\Application\Transformer\File\UploadFileErrorTransformer;
use App\Module\System\Application\Transformer\ImportLog\ImportLogErrorTransformer;
use App\Module\System\Domain\Entity\File;
use App\Module\System\Domain\Enum\Import\ImportKindEnum;
use App\Module\System\Domain\Enum\Import\ImportStatusEnum;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LogLevel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract readonly class AbstractImportFacade
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected ValidatorInterface $validator,
        protected Security $security,
        protected ParameterBagInterface $params,
        protected MessageService $messageService,
        #[Autowire(service: 'event.bus')] protected MessageBusInterface $eventBus,
        #[Autowire(service: 'command.bus')] protected MessageBusInterface $commandBus,
        #[Autowire(service: 'query.bus')] protected MessageBusInterface $queryBus,
    ) {
    }

    public function handle(
        UploadedFile $file,
        string $folder,
        ImportKindEnum $importKind,
        string $successMessage,
        string $errorMessage,
        callable $importCommand
    ): array {
        $this->entityManager->beginTransaction();

        try {
            $user = $this->security->getUser();
            $uploadFilePath = sprintf('%s/%s', $this->params->get('upload_import_file_path'), $folder);
            $fileName = UploadFile::generateUniqueFileName(FileExtensionEnum::XLSX->value);

            $uploadFileDTO = new UploadFileDTO($file, $uploadFilePath, $fileName);

            $errors = $this->validator->validate($uploadFileDTO);
            if (count($errors) > 0) {
                return [
                    'success' => false,
                    'errors' => UploadFileErrorTransformer::map($errors),
                    'message' => $errorMessage,
                ];
            }

            try {
                $this->commandBus->dispatch(new UploadFileCommand(
                    file: $uploadFileDTO->file,
                    uploadFilePath: $uploadFileDTO->uploadFilePath,
                    uploadFileName: $uploadFileDTO->uploadFileName
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

                $handleStamp = $this->queryBus->dispatch(new GetFileByNamePathAndKindQuery(
                    fileName: $fileName,
                    filePath: $uploadFilePath,
                    fileKind: FileKindEnum::IMPORT_XLSX
                ));

                $fileEntity = $handleStamp->last(HandledStamp::class)->getResult();

                $this->commandBus->dispatch(new CreateImportCommand(
                    kindEnum: $importKind,
                    statusEnum: ImportStatusEnum::PENDING,
                    file: $fileEntity,
                    user: $user
                ));

                $handleStamp = $this->queryBus->dispatch(new GetImportByFileQuery($fileEntity));
                $import = $handleStamp->last(HandledStamp::class)->getResult();

                $importCommand($import);

            } catch (HandlerFailedException $exception) {
                throw $exception->getPrevious();
            }

            $this->entityManager->commit();

            if (ImportStatusEnum::DONE === $import->getStatus()) {
                return [
                    'success' => true,
                    'message' => $successMessage,
                ];
            }

            $handleStamp = $this->queryBus->dispatch(new GetImportLogsByImportQuery($import));
            $importLogs = $handleStamp->last(HandledStamp::class)->getResult();

            return [
                'success' => false,
                'errors' => ImportLogErrorTransformer::map($importLogs),
                'message' => $errorMessage,
            ];
        } catch (\Throwable $error) {
            $this->entityManager->rollback();
            $this->eventBus->dispatch(new LogFileEvent(
                sprintf('%s %s', $errorMessage, $this->messageService->get($error->getMessage())),
                LogLevel::ERROR,
                MonologChannelEnum::IMPORT
            ));

            return [
                'success' => false,
                'message' => $errorMessage,
                'errors' => [],
            ];
        }
    }
}
