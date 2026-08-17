<?php

declare(strict_types=1);

namespace App\Module\Company\Application\QueryHandler\User;

use App\Module\Company\Application\Query\User\UserDataQuery;
use App\Module\Company\Application\ReadModel\Employee\EmployeeView;
use App\Module\Company\Application\ReadModel\User\MeView;
use App\Module\Company\Domain\Aggregate\Employee\ValueObject\EmployeeUUID;
use App\Module\Company\Domain\Entity\Employee;
use App\Module\Company\Domain\Entity\User;
use App\Module\Company\Domain\Interface\Employee\EmployeeAggregateReaderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class UserDataQueryHandler
{
    public function __construct(
        private Security $security,
        private EmployeeAggregateReaderInterface $employeeAggregateReaderRepository,
    ) {
    }

    public function __invoke(UserDataQuery $query): MeView
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException();
        }

        $employeeView = null;
        $employee = $user->getEmployee();

        if ($employee instanceof Employee) {
            $employeeAggregate = $this->employeeAggregateReaderRepository->getEmployeeAggregateByUUID(
                EmployeeUUID::fromString($employee->getUUID()->toString())
            );

            $avatarPath = $employeeAggregate->getAvatarPath();
            $avatarType = $employeeAggregate->getAvatarType();
            $defaultAvatar = $employeeAggregate->getDefaultAvatar();

            $employeeView = EmployeeView::fromEmployee(
                employee: $employee,
                avatarType: $avatarType,
                defaultAvatar: $defaultAvatar,
                avatarPath: $avatarPath
            );
        }

        return new MeView(
            userUUID: $user->getUUID()->toString(),
            email: $user->getEmail(),
            employee: $employeeView
        );
    }
}
