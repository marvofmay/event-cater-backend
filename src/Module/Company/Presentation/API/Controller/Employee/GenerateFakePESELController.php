<?php

declare(strict_types=1);

namespace App\Module\Company\Presentation\API\Controller\Employee;

use App\Common\Shared\Utils\PESELGenerator;
use App\Module\Company\Domain\Interface\Employee\EmployeeReaderInterface;
use App\Module\System\Domain\Enum\Access\AccessEnum;
use App\Module\System\Domain\Enum\Permission\PermissionEnum;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class GenerateFakePESELController extends AbstractController
{
    #[Route(path: '/api/employees/generate-fake-pesel', name: 'api.employees.generate_fake_pesel', methods: ['POST'])]
    public function __invoke(PESELGenerator $generator, EmployeeReaderInterface $employees): JsonResponse
    {
        $this->denyAccessUnlessGranted(PermissionEnum::CREATE, AccessEnum::EMPLOYEES);

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $pesel = $generator->generate();
            if (null === $employees->getEmployeeByPESEL($pesel)) {
                return new JsonResponse(['pesel' => $pesel, 'isPeselFake' => true]);
            }
        }

        throw new ConflictHttpException('Nie udało się wygenerować wolnego numeru. Spróbuj ponownie.');
    }
}
