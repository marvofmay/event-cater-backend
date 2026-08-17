<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Company;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Company\UpdateCompanyCommand;
use App\Module\Company\Application\DTO\Company\UpdateDTO;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[ErrorChannel(MonologChannelEnum::EVENT_LOG)]
final class UpdateCompanyController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
        private readonly MessageService $messageService,
    ) {
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    #[Route(
        path: '/api/companies/{uuid}',
        name: 'api.company.update',
        requirements: ['uuid' => '[0-9a-fA-F-]{36}'],
        methods: ['PUT']
    )]
    public function __invoke(string $uuid, #[MapRequestPayload] UpdateDTO $updateDTO): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::UPDATE,
            AccessEnum::COMPANIES,
            $this->messageService->get('accessDenied')
        );

        try {
            $this->commandBus->dispatch(
                new UpdateCompanyCommand(
                    $uuid,
                    $updateDTO->fullName,
                    $updateDTO->shortName,
                    $updateDTO->internalCode,
                    $updateDTO->active,
                    $updateDTO->parentCompanyUUID,
                    $updateDTO->nip,
                    $updateDTO->regon,
                    $updateDTO->description,
                    $updateDTO->industryUUID,
                    $updateDTO->phones,
                    $updateDTO->emails,
                    $updateDTO->websites,
                    $updateDTO->address
                )
            );
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }

        return new JsonResponse(
            ['message' => $this->messageService->get('company.update.success', [], 'companies')],
            Response::HTTP_OK
        );
    }
}
