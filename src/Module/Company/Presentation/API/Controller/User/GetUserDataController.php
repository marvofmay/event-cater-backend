<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\User;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Query\User\UserDataQuery;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[ErrorChannel(MonologChannelEnum::EVENT_LOG)]
final class GetUserDataController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'query.bus')] private readonly MessageBusInterface $queryBus,
    ) {
    }

    /**
     * @throws Throwable
     * @throws ExceptionInterface
     */
    #[Route(path: '/api/me/data', name: 'api.me.data', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $stamp = $this->queryBus->dispatch(new UserDataQuery());
            $data = $stamp->last(HandledStamp::class)->getResult();
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious();
        }

        return new JsonResponse(['data' => $data], Response::HTTP_OK);
    }
}
