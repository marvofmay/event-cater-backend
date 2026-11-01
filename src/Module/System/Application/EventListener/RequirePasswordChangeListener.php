<?php

declare(strict_types=1);

namespace App\Module\System\Application\EventListener;

use App\Module\Company\Domain\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final readonly class RequirePasswordChangeListener
{
    public function __construct(private Security $security) {}

    #[AsEventListener(event: 'kernel.request', priority: 0)]
    public function onRequest(RequestEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        if (!$event->isMainRequest() || !str_starts_with($path, '/api/') || in_array($path, ['/api/login', '/api/logout', '/api/auth/change-password'], true)) {
            return;
        }
        $user = $this->security->getUser();
        if ($user instanceof User && $user->mustChangePassword()) {
            $event->setResponse(new JsonResponse(['code' => 'PASSWORD_CHANGE_REQUIRED', 'message' => 'Przed korzystaniem z aplikacji zmień hasło startowe.'], 423, ['Cache-Control' => 'no-store']));
        }
    }
}
