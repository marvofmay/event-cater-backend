<?php

declare(strict_types=1);

namespace App\Module\System\Presentation\API\Controller\Auth;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\System\Application\Command\Auth\LogoutCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[ErrorChannel(MonologChannelEnum::EVENT_LOG)]
class LogoutController extends AbstractController
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
    #[Route(path: '/api/logout', name: 'api.logout', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new LogoutCommand($request));
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }

        return new JsonResponse(
            data: ['message' => $this->messageService->get('logout.success', [], 'security')],
            status: Response::HTTP_OK
        );
    }
}
