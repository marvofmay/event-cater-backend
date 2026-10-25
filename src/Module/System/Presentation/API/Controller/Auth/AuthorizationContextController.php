<?php

declare(strict_types=1);

namespace App\Module\System\Presentation\API\Controller\Auth;

use App\Module\System\Domain\Service\User\UserAuthorizationInfoProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AuthorizationContextController extends AbstractController
{
    #[Route('/api/auth/authorization', methods: ['GET'])]
    public function __invoke(UserAuthorizationInfoProvider $authorization): JsonResponse
    {
        $user = $this->getUser();

        return $this->json([
            'modules' => $authorization->getModules($user),
            'accesses' => $authorization->getAccesses($user),
            'permissions' => $authorization->getPermissions($user),
        ]);
    }
}
