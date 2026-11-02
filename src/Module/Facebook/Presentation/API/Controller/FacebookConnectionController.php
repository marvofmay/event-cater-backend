<?php

declare(strict_types=1);

namespace App\Module\Facebook\Presentation\API\Controller;

use App\Common\Presentation\Http\PlanningController;
use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Facebook\Application\FacebookConnectionService;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/facebook')]
final class FacebookConnectionController extends PlanningController
{
    #[Route('/connection', methods: ['GET'])]
    public function status(FacebookConnectionService $connections): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::PUBLISH_FACEBOOK, AccessEnum::EVENTS);

        return $this->resource($connections->status());
    }

    #[Route('/connect-url', methods: ['GET'])]
    public function connectUrl(FacebookConnectionService $connections): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::PUBLISH_FACEBOOK, AccessEnum::EVENTS);

        return $this->resource($connections->connectUrl($this->actor()));
    }

    #[Route('/callback', methods: ['POST'])]
    public function callback(Request $request, FacebookConnectionService $connections): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::PUBLISH_FACEBOOK, AccessEnum::EVENTS);
        $body = PlanningPayload::json($request);

        return $this->resource($connections->completeOAuth(
            $body->text('code') ?? '',
            $body->text('state') ?? '',
            $this->actor(),
            $body->text('pageId', false),
        ));
    }

    #[Route('/select-page', methods: ['POST'])]
    public function selectPage(Request $request, FacebookConnectionService $connections): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::PUBLISH_FACEBOOK, AccessEnum::EVENTS);
        $body = PlanningPayload::json($request);

        return $this->resource($connections->selectPendingPage(
            $body->text('pendingToken') ?? '',
            $body->text('pageId') ?? '',
            $this->actor(),
        ));
    }

    #[Route('/connection', methods: ['DELETE'])]
    public function disconnect(FacebookConnectionService $connections): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::PUBLISH_FACEBOOK, AccessEnum::EVENTS);
        $connections->disconnect();

        return $this->resource(['connected' => false]);
    }
}
