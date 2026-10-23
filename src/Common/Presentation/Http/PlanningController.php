<?php

declare(strict_types=1);

namespace App\Common\Presentation\Http;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

abstract class PlanningController extends AbstractController
{
    public function __construct(
        #[Autowire(service: 'command.bus')] private readonly MessageBusInterface $commands,
        #[Autowire(service: 'query.bus')] private readonly MessageBusInterface $queries,
    ) {
    }

    protected function execute(object $command): void
    {
        $this->commands->dispatch($command);
    }
    protected function ask(object $query): array
    {
        $stamp = $this->queries->dispatch($query)->last(HandledStamp::class);
        if (!$stamp) {
            throw new \LogicException('Query has no handler.');
        }
        return $stamp->getResult();
    }
    protected function actor(): string
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Module\Company\Domain\Entity\User) {
            throw $this->createAccessDeniedException();
        }
        return $user->getUUID()->toString();
    }
    protected function resource(array $data, int $status = 200): JsonResponse
    {
        $response = $this->json($data, $status);
        if (isset($data['version'])) {
            $response->setEtag((string) $data['version']);
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        return $response;
    }
}
