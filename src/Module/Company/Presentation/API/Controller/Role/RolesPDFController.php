<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Role;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\DTO\Role\RolesPDFQueryDTO;
use App\Module\Company\Application\Query\Role\GetRolesPDFQuery;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[ErrorChannel(MonologChannelEnum::EVENT_LOG)]
final class RolesPDFController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'query.bus')] private readonly MessageBusInterface $queryBus,
        private readonly MessageService $messageService,
    ) {
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    #[Route(path: '/api/roles/pdf', name: 'api.roles.pdf', methods: ['GET'])]
    public function __invoke(#[MapQueryString] RolesPDFQueryDTO $dto): Response
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::PDF,
            AccessEnum::ROLES,
            $this->messageService->get('accessDenied')
        );

        try {
            $pdf = $this->queryBus->dispatch(new GetRolesPDFQuery($dto->uuids))
                ->last(HandledStamp::class)
                ->getResult();
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious();
        }

        return new Response(
            $pdf,
            Response::HTTP_OK,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="roles.pdf"',]
        );
    }
}
