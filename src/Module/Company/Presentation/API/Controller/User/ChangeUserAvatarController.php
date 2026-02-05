<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\User;

use App\Common\Domain\Enum\MonologChanelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Command\Employee\ChangeEmployeeAvatarCommand;
use App\Module\Company\Application\DTO\User\ChangeUserAvatarDTO;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[ErrorChannel(MonologChanelEnum::EVENT_STORE)]
final class ChangeUserAvatarController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commandBus,
        private readonly MessageService $messageService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('/api/me/avatar', name: 'api.me.avatar', methods: ['PATCH'])]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $dto = new ChangeUserAvatarDTO();
            $dto->type = $request->request->get('type', 'default');
            $dto->defaultAvatar = $request->request->get('defaultAvatar');
            $dto->uploadedFile = $request->files->get('uploadedFile');

            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                // ToDo::
                // obsługa błędów
            }

            $this->commandBus->dispatch(
                new ChangeEmployeeAvatarCommand(
                    avatarType: $dto->type,
                    defaultAvatar: $dto->defaultAvatar,
                    uploadedFile: $dto->uploadedFile
                )
            );

        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious();
        }

        return new JsonResponse(['message' => $this->messageService->get('user.data.avatar.update.success', [], 'users')], Response::HTTP_OK);
    }
}
