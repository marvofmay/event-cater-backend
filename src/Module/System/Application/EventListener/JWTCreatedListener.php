<?php

declare(strict_types=1);

namespace App\Module\System\Application\EventListener;

use App\Module\System\Application\Event\Auth\UserLoginEvent;
use App\Module\System\Domain\Enum\Auth\AuthEventTypeEnum;
use App\Module\System\Domain\Service\AuthEvent\AuthEventRecorder;
use App\Module\System\Domain\Service\User\UserAuthorizationInfoProvider;
use App\Module\System\Domain\Service\User\UserPersonalInfoProvider;
use App\Module\System\Domain\ValueObject\TokenUUID;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created', method: 'onJWTCreated')]
final readonly class JWTCreatedListener
{
    public function __construct(
        private \App\Module\Saas\Infrastructure\TenantContext $tenantContext,
        private AuthEventRecorder $authEventRecorder,
        private EventDispatcherInterface $eventDispatcher,
        private UserAuthorizationInfoProvider $userAuthInfoProvider,
        private UserPersonalInfoProvider $userPersonalInfoProvider,
        private \Doctrine\ORM\EntityManagerInterface $em
    ) {
    }

    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getData();

        if ($user->getSaasAdministratorUuid() !== null) {
            $this->em->persist($user);
            $this->em->flush();
            $payload['saasAdministratorUuid'] = $user->getSaasAdministratorUuid();
        }

        $tenant = $this->tenantContext->current();
        $payload['tenantUuid'] = $tenant['uuid'];
        $payload['tenantSlug'] = $tenant['slug'];
        $payload['tenantName'] = $tenant['name'];
        $payload['tenantVersion'] = (int)$tenant['version'];

        $payload['user'] = $this->userPersonalInfoProvider->getUserInfo($user);
        $payload['employee'] = $this->userPersonalInfoProvider->getEmployeeInfo($user);
        $payload['roles'] = $this->userPersonalInfoProvider->getRoles($user);
        $payload['modules'] = $this->userAuthInfoProvider->getModules($user);
        $payload['accesses'] = $this->userAuthInfoProvider->getAccesses($user);
        $payload['permissions'] = $this->userAuthInfoProvider->getPermissions($user);

        $tokenUUID = TokenUUID::generate();
        $payload['tokenUUID'] = $tokenUUID->toString();

        $event->setData($payload);

        $this->authEventRecorder->record(
            user: $user,
            type: AuthEventTypeEnum::LOGIN,
            tokenUUID: $tokenUUID
        );

        $this->eventDispatcher->dispatch(new UserLoginEvent([
            'tokenUUID'    => $tokenUUID->toString(),
            'userUUID'     => $user->getUUID()->toString(),
        ]));
    }
}
