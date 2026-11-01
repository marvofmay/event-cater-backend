<?php

declare(strict_types=1);

namespace App\Module\System\Presentation\API\Controller\Auth;

use App\Module\Saas\Infrastructure\TenantContext;
use App\Module\System\Application\Command\Auth\RequestPasswordResetCommand;
use App\Module\System\Application\Command\Auth\PasswordChangedNoticeCommand;
use App\Module\System\Domain\Service\User\PasswordRecoveryService;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class PasswordRecoveryController
{
    public const ACCEPTED = 'Jeśli konto w wybranej firmie ma przypisany adres e-mail, otrzymasz wiadomość z linkiem do ustawienia nowego hasła.';

    public function __construct(
        private TenantContext $tenant,
        #[Autowire(service: 'command.bus')] private MessageBusInterface $bus,
        #[Autowire(service: 'limiter.password_reset_ip')] private RateLimiterFactoryInterface $ipLimiter,
        #[Autowire(service: 'limiter.password_reset_account')] private RateLimiterFactoryInterface $accountLimiter,
        private PasswordRecoveryService $recovery,
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/auth/password-reset/request', methods: ['POST'])]
    public function request(Request $request): JsonResponse
    {
        if (!$this->ipLimiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) return $this->response('Zbyt wiele prób. Spróbuj ponownie za 15 minut.', 429);
        $data = $request->toArray();
        if (!is_string($data['login'] ?? null) || trim($data['login']) === '' || strlen($data['login']) > 254) return $this->response('Podaj login lub adres e-mail.', 422);
        try { $login = PasswordRecoveryService::normalizeLogin($data['login']); }
        catch (\InvalidArgumentException) { return $this->response(self::ACCEPTED, 202); }
        $key = hash('sha256', $this->tenant->current()['uuid'].'|'.$login);
        if ($this->accountLimiter->create($key)->consume()->isAccepted()) {
            $this->bus->dispatch(new RequestPasswordResetCommand($login, Uuid::uuid4()->toString(), time()));
        }

        return $this->response(self::ACCEPTED, 202);
    }

    #[Route('/api/auth/password-reset/confirm', methods: ['POST'])]
    public function confirm(Request $request): JsonResponse
    {
        if (!$this->ipLimiter->create('confirm:'.($request->getClientIp() ?? 'unknown'))->consume()->isAccepted()) return $this->response('Zbyt wiele prób. Spróbuj ponownie za 15 minut.', 429);
        $data = $request->toArray();
        foreach (['token', 'newPassword', 'confirmation'] as $field) {
            if (!is_string($data[$field] ?? null) || strlen($data[$field]) > 1024) return $this->response('Podaj link resetowania, nowe hasło i jego potwierdzenie.', 422);
        }
        $user = $this->recovery->confirm($data['token'], $data['newPassword'], $data['confirmation']);
        try { $this->bus->dispatch(new PasswordChangedNoticeCommand($user['userUuid'], $user['email'])); }
        catch (\Throwable) { $this->logger->error('Could not queue password change notice.'); }

        return $this->response('Hasło zostało zmienione. Zaloguj się nowym hasłem.');
    }

    private function response(string $message, int $status = 200): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status, ['Cache-Control' => 'no-store', 'Referrer-Policy' => 'no-referrer']);
    }
}
