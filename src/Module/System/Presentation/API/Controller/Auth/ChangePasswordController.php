<?php

declare(strict_types=1);

namespace App\Module\System\Presentation\API\Controller\Auth;

use App\Module\Company\Domain\Entity\User;
use App\Module\System\Domain\Service\User\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use App\Module\Saas\Infrastructure\TenantContext;

final class ChangePasswordController extends AbstractController
{
    #[Route('/api/auth/change-password', methods: ['POST'])]
    public function __invoke(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher, JWTTokenManagerInterface $tokens, TenantContext $tenant, #[Autowire(service: 'limiter.password_change')] RateLimiterFactoryInterface $limiter): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User || $user->getSaasAdministratorUuid() !== null) {
            return new JsonResponse(['message' => 'Hasło administratora technicznego jest zarządzane w panelu SaaS.'], 403);
        }
        if (!$limiter->create($tenant->current()['uuid'].':'.$user->getUUID()->toString())->consume()->isAccepted()) {
            return new JsonResponse(['message' => 'Zbyt wiele prób zmiany hasła. Spróbuj ponownie za minutę.'], 429, ['Retry-After' => '60']);
        }
        $input = $request->toArray();
        foreach (['currentPassword', 'newPassword', 'confirmation'] as $field) {
            if (!isset($input[$field]) || !is_string($input[$field]) || strlen($input[$field]) > 1024) {
                return new JsonResponse(['message' => 'Podaj obecne hasło, nowe hasło i jego potwierdzenie.'], 422);
            }
        }
        if (!$hasher->isPasswordValid($user, $input['currentPassword'])) {
            return new JsonResponse(['message' => 'Obecne hasło jest nieprawidłowe.'], 422);
        }
        $errors = PasswordPolicy::errors($input['newPassword'], $input['confirmation']);
        if ($hasher->isPasswordValid($user, $input['newPassword'])) {
            $errors[] = 'Nowe hasło musi być inne niż dotychczasowe.';
        }
        if ($errors !== []) {
            return new JsonResponse(['message' => implode(' ', $errors)], 422);
        }
        $hash = $hasher->hashPassword($user, $input['newPassword']);
        $updated = $em->getConnection()->executeStatement('UPDATE user SET password = ?, must_change_password = 0, credential_version = credential_version + 1 WHERE uuid = ? AND credential_version = ?', [$hash, $user->getUUID()->toString(), $user->getCredentialVersion()]);
        if ($updated !== 1) {
            return new JsonResponse(['message' => 'Dane logowania zmieniły się. Zaloguj się ponownie.'], 409);
        }
        $em->refresh($user);

        return new JsonResponse(['message' => 'Hasło zostało zmienione.', 'token' => $tokens->create($user)], 200, ['Cache-Control' => 'no-store']);
    }
}
