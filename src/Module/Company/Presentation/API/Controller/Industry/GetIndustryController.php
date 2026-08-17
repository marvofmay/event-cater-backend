<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Industry;

use App\Common\Domain\Enum\MonologChannelEnum;
use App\Common\Domain\Service\MessageTranslator\MessageService;
use App\Common\Infrastructure\Http\Attribute\ErrorChannel;
use App\Module\Company\Application\Query\Industry\GetIndustryByUUIDQuery;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
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
final class GetIndustryController extends AbstractController
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
    #[Route(
        path: '/api/industries/{uuid}',
        name: 'api.industries.get',
        requirements: ['uuid' => '[0-9a-fA-F-]{36}'],
        methods: ['GET']
    )]
    public function __invoke(string $uuid): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PermissionEnum::VIEW,
            AccessEnum::INDUSTRIES,
            $this->messageService->get('accessDenied')
        );

        try {
            $handledStamp = $this->queryBus->dispatch(new GetIndustryByUUIDQuery($uuid));
            $data = $handledStamp->last(HandledStamp::class)->getResult();
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }

        return new JsonResponse(['data' => $data], Response::HTTP_OK);
    }
}
