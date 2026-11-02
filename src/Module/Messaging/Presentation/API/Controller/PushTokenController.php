<?php

declare(strict_types=1);

namespace App\Module\Messaging\Presentation\API\Controller;

use App\Common\Domain\Exception\BusinessRuleViolation;
use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Messaging\Application\Command\RegisterPushToken;
use App\Module\Messaging\Application\Command\UnregisterPushToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/me/push-tokens')]
final class PushTokenController extends PlanningController
{
    #[Route('', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $body = PlanningPayload::json($request);
        $this->execute(new RegisterPushToken(
            $this->actor(),
            $body->text('token'),
            $body->text('platform'),
        ));

        return $this->resource(['message' => 'Zarejestrowano token push.']);
    }

    #[Route('', methods: ['DELETE'])]
    public function unregister(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $body = PlanningPayload::json($request);
        $token = $body->text('token', false);
        if ($token === null || trim($token) === '') {
            throw new BusinessRuleViolation('Wymagany token push.');
        }
        $this->execute(new UnregisterPushToken($this->actor(), $token));

        return $this->resource(['message' => 'Usunięto token push.']);
    }
}
