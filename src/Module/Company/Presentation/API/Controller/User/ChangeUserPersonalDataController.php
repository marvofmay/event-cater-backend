<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\User;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Employee\ChangeEmployeePersonalDataCommand;
use App\Module\Company\Application\DTO\User\ChangeUserPersonalDataDTO;
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

#[ErrorChannel(MonologChannelEnum::EVENT_STORE)]
final class ChangeUserPersonalDataController extends AbstractController
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
    #[Route(path: '/api/me/personal', name: 'api.me.personal', methods: ['PATCH'])]
    public function __invoke(#[MapRequestPayload] ChangeUserPersonalDataDTO $changeUserPersonalDataDTO): JsonResponse
    {
        try {
            $this->commandBus->dispatch(
                new ChangeEmployeePersonalDataCommand(
                    firstName: $changeUserPersonalDataDTO->firstName,
                    lastName: $changeUserPersonalDataDTO->lastName,
                )
            );
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious();
        }

        return new JsonResponse(
            ['message' => $this->messageService->get('user.data.personal.update.success', [], 'users')], 
            Response::HTTP_OK
        );
    }
}
