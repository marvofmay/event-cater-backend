<?php

declare(strict_types=1);

namespace App\Module\System\Infrastructure\Preference;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;

final readonly class DisablePreferenceProfilingListener
{
    public function __construct(private ?Profiler $profiler = null)
    {
    }

    #[AsEventListener(event: 'kernel.request', priority: 64)]
    public function onRequest(RequestEvent $event): void
    {
        if (str_starts_with($event->getRequest()->getPathInfo(), '/api/preferences/') || str_starts_with($event->getRequest()->getPathInfo(), '/api/auth/password-reset/') || in_array($event->getRequest()->getPathInfo(), ['/api/login', '/api/auth/change-password'], true)) {
            $this->profiler?->disable();
        }
    }
}
