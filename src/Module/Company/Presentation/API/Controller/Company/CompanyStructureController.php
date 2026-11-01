<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Company;

use App\Module\Company\Infrastructure\ReadModel\CompanyStructureReader;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class CompanyStructureController extends AbstractController
{
    #[Route('/api/company-structure', name: 'api.company.structure', methods: ['GET'])]
    public function __invoke(Request $request, CompanyStructureReader $reader): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::COMPANY_STRUCTURE);
        $mode = $request->query->getString('mode', 'children');
        $type = $request->query->getString('parentType', 'root');
        $uuid = $request->query->getString('parentUuid');
        $page = filter_var($request->query->get('page', '1'), FILTER_VALIDATE_INT);
        $pageSize = filter_var($request->query->get('pageSize', '30'), FILTER_VALIDATE_INT);
        $phrase = trim($request->query->getString('phrase'));
        if (!in_array($type, ['root', 'company', 'department'], true)
            || ('root' !== $type && !Uuid::isValid($uuid))
            || false === $page || $page < 1 || $page > 100000
            || false === $pageSize || $pageSize < 1 || $pageSize > 50
            || mb_strlen($phrase) > 100) {
            throw new BadRequestHttpException('Nieprawidłowe parametry drzewa firmy.');
        }

        $departments = true;
        $employees = true;
        if (!in_array($mode, ['children', 'search', 'path'], true)) {
            throw new BadRequestHttpException('Nieprawidłowy tryb wyszukiwania.');
        }
        if ('search' === $mode) {
            if (mb_strlen($phrase) < 2) {
                throw new BadRequestHttpException('Wpisz co najmniej 2 znaki.');
            }

            return $this->json(['data' => $reader->search($phrase, $page, $pageSize, $departments, $employees)], headers: ['Cache-Control' => 'private, no-store']);
        }
        if ('path' === $mode) {
            $nodeType = $request->query->getString('nodeType');
            $nodeUuid = $request->query->getString('nodeUuid');
            if (!in_array($nodeType, ['company', 'department', 'employee'], true) || !Uuid::isValid($nodeUuid)) {
                throw new BadRequestHttpException('Nieprawidłowy element struktury.');
            }

            return $this->json(['data' => $reader->path($nodeType, $nodeUuid)], headers: ['Cache-Control' => 'private, no-store']);
        }

        return $this->json(['data' => $reader->children($type, $uuid ?: null, $page, $pageSize, $phrase, $departments, $employees)], headers: [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    #[Route('/api/company-structure/employees/{uuid}', methods: ['GET'])]
    public function employee(string $uuid, CompanyStructureReader $reader): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::VIEW, AccessEnum::COMPANY_STRUCTURE);
        if (!Uuid::isValid($uuid)) throw new BadRequestHttpException('Nieprawidłowy pracownik.');

        return $this->json(['data' => $reader->employeePreview($uuid)], headers: ['Cache-Control' => 'private, no-store']);
    }
}
