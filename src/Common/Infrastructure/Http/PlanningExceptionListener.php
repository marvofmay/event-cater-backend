<?php

declare(strict_types=1);

namespace App\Common\Infrastructure\Http;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\Exception\PlanningConflict;
use Doctrine\DBAL\Exception\RetryableException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener(event: 'kernel.exception', priority: 400)]
final class PlanningExceptionListener
{
    public function __construct(private readonly TokenStorageInterface $tokens, private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if (!preg_match('#^/api/(event-venues|kitchen-orders|events|equipment|equipment-categories|consumables|preparation-templates|preparation-options)(/|$)#', $event->getRequest()->getPathInfo())) {
            return;
        }
        $error = $event->getThrowable();
        while ($error instanceof HandlerFailedException && $error->getPrevious()) {
            $error = $error->getPrevious();
        }
        [$status, $message] = match (true) {
            $error instanceof BusinessRuleViolation => [422, $error->getMessage()],
            $error instanceof PlanningResourceNotFound => [404, $error->getMessage()],
            $error instanceof PlanningConflict => [409, $error->getMessage()],
            $error instanceof UniqueConstraintViolationException => [409, 'A record with this unique identifier already exists.'],
            $error instanceof OptimisticLockException, $error instanceof RetryableException => [409, 'Concurrent change. Reload and retry.'],
            $error instanceof AccessDeniedException => $this->tokens->getToken()?->getUser() === null ? [401, 'JWT required.'] : [403, 'Access denied.'],
            $error instanceof HttpExceptionInterface => [$error->getStatusCode(), $error->getMessage()],
            default => [500, 'Unexpected server error.'],
        };
        if ($status === 500) {
            $this->logger->error('Planning API failed.', ['exception' => $error]);
        }
        $headers = $status === 401 ? ['WWW-Authenticate' => 'Bearer'] : [];
        $event->setResponse(new JsonResponse(['message' => $message], $status, $headers));
    }
}
