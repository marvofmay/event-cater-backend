<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Department;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Department\RestoreDepartmentCommand;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[ErrorChannel(MonologChannelEnum::EVENT_STORE)]
final class RestoreDepartmentController extends AbstractController
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
        path: '/api/departments/{uuid}/restore',
        name: 'api.departments.restore',
        requirements: ['uuid' => '[0-9a-fA-F-]{36}'],
        methods: ['PATCH']
    )]
    public function __invoke(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::RESTORE,
            AccessEnum::COMPANIES,
            $this->messageService->get('accessDenied')
        );

        try {
            $this->commandBus->dispatch(
                new RestoreDepartmentCommand($uuid)
            );
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        return new JsonResponse(
            ['message' => $this->messageService->get('department.restore.success', [], 'departments')],
            Response::HTTP_OK
        );
    }
}
