<?php

declare(strict_types=1);

namespace App\Common\Infrastructure\Http;

use App\Common\Application\Exception\PlanningResourceNotFound;
use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Domain\Exception\PlanningConflict;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
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
        if (!preg_match('#^/api/(file-manager|messages|event-customers|event-venues|kitchen-orders|events|equipment|equipment-categories|equipment-locations|consumables|consumable-categories|preparation-templates|preparation-options|my-hours|employee-hours|facebook|company-notices|company-documents|support|saas/support)(/|$)#', $event->getRequest()->getPathInfo())) {
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
            $error instanceof NotNullConstraintViolationException => [422, $this->notNullMessage($error)],
            $error instanceof OptimisticLockException, $error instanceof RetryableException => [409, 'Concurrent change. Reload and retry.'],
            $error instanceof AccessDeniedException => $this->tokens->getToken()?->getUser() === null ? [401, 'JWT required.'] : [403, 'Access denied.'],
            $error instanceof HttpExceptionInterface => [$error->getStatusCode(), $error->getMessage()],
            default => [500, $this->serverMessage($error)],
        };
        if ($status === 500) {
            $this->logger->error('Planning API failed.', ['exception' => $error]);
        }
        $headers = $status === 401 ? ['WWW-Authenticate' => 'Bearer'] : [];
        $event->setResponse(new JsonResponse(['message' => $message], $status, $headers));
    }

    private function serverMessage(\Throwable $error): string
    {
        if (str_contains($error->getMessage(), 'creation_notice_sent_at')
            || str_contains($error->getMessage(), 'notification_channels')
            || str_contains($error->getMessage(), 'event_notification_recipient_override')
            || str_contains($error->getMessage(), 'due_reminder_sent_at')) {
            return 'Wysłanie powiadomienia nie przeszło, bo w bazie firmy brakuje tabeli albo kolumny odbiorców. Uruchom migracje baz firm.';
        }

        return 'Unexpected server error.';
    }

    private function notNullMessage(NotNullConstraintViolationException $error): string
    {
        if (str_contains($error->getMessage(), "Column 'code'")) {
            return 'Zapis bez kodu nie przeszedł, bo kolumna kodu w bazie firmy nadal jest wymagana. Wpisz kod albo uruchom migracje baz firm.';
        }

        return 'Zapis odrzucony, bo wymagane pole w bazie jest puste.';
    }
}
