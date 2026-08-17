<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Role;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Role\AssignPermissionsCommand;
use App\Module\Company\Application\DTO\Role\AssignPermissionsDTO;
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
final class AssignPermissionController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
        private readonly MessageService $messageService
    ) {
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    #[Route(
        path: '/api/roles/{uuid}/accesses/permissions',
        name: 'api.roles.accesses.permissions.create',
        methods: ['POST']
    )]
    public function __invoke(string $uuid, #[MapRequestPayload] AssignPermissionsDTO $dto): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::ASSIGN_PERMISSION_TO_ACCESS_ROLE,
            AccessEnum::ROLES,
            $this->messageService->get('accessDenied')
        );

        try {
            $this->commandBus->dispatch(new AssignPermissionsCommand(roleUUID: $uuid, accesses: $dto->accesses));
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }

        return new JsonResponse(
            ['message' => $this->messageService->get('role.assign.permission.success', [], 'roles')],
            Response::HTTP_CREATED
        );
    }
}
