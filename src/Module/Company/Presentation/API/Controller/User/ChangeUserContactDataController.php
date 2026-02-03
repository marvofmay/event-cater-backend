<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\User;

use App\Common\Domain\Enum\MonologChanelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Employee\ChangeEmployeeContactCommand;
use App\Module\Company\Application\DTO\User\ChangeUserContactDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[ErrorChannel(MonologChanelEnum::EVENT_STORE)]
final class ChangeUserContactDataController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
        private readonly MessageService $messageService,
    ) {
    }

    #[Route('/api/me/contact', name: 'api.me.contact', methods: ['PATCH'])]
    public function __invoke(#[MapRequestPayload] ChangeUserContactDTO $changeUserContactDTO): JsonResponse
    {
        try {
            $this->commandBus->dispatch(
                new ChangeEmployeeContactCommand(
                    $changeUserContactDTO->email,
                    $changeUserContactDTO->phones
                )
            );
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious();
        }

        return new JsonResponse(['message' => $this->messageService->get('user.data.contact.update.success', [], 'users')], Response::HTTP_OK);
    }
}
