<?php

declare(strict_types=1);

namespace App\Module\Saas\Infrastructure;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final readonly class TenantRequestListener
{
    public function __construct(private TenantContext $context)
    {
    }

    #[AsEventListener(event: 'kernel.request', priority: 64)]
    public function request(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api') || str_starts_with($path, '/api/saas') || str_starts_with($path, '/api/doc')) {
            return;
        }
        $this->context->select($request->headers->get('X-Tenant', 'default'));
    }

    #[AsEventListener(event: 'kernel.exception', priority: 128)]
    public function exception(ExceptionEvent $event): void
    {
        if (!str_starts_with($event->getRequest()->getPathInfo(), '/api')) {
            return;
        }
        $error = $event->getThrowable();
        if ($error instanceof HttpExceptionInterface) {
            $event->setResponse(new JsonResponse(['message' => $error->getMessage()], $error->getStatusCode(), $error->getHeaders()));
        }
    }
}
