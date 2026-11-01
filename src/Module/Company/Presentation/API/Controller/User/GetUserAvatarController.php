<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\User;

use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Interface\Employee\EmployeeAggregateReaderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class GetUserAvatarController extends AbstractController
{
    #[Route(path: '/api/me/avatar', name: 'api.me.avatar.get', methods: ['GET'])]
    public function __invoke(EmployeeAggregateReaderInterface $employees): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $employee = $this->getUser()->getEmployee();
        $avatar = null;
        if ($employee !== null) {
            $aggregate = $employees->getEmployeeAggregateByUUID(EmployeeUUID::fromString($employee->getUUID()->toString()));
            $avatar = [
                'avatarType' => $aggregate->getAvatarType(),
                'defaultAvatar' => $aggregate->getDefaultAvatar(),
                'avatarPath' => $aggregate->getAvatarPath(),
            ];
        }

        return $this->json(['data' => $avatar], headers: ['Cache-Control' => 'private, no-store']);
    }
}
