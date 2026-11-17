<?php

declare(strict_types=1);

namespace App\Module\Support\Infrastructure;

use App\Module\Company\Domain\Entity\User;
use App\Module\Saas\Infrastructure\TenantContext;
use App\Module\Support\Application\Reporter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class CurrentReporter
{
    public function __construct(private Security $security, private TenantContext $tenants)
    {
    }

    public function require(): Reporter
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedHttpException('Zaloguj się, aby korzystać z pomocy.');
        }
        $tenant = $this->tenants->current();
        $employee = $user->getEmployee();
        $name = $employee ? trim($employee->getFirstName().' '.$employee->getLastName()) : '';
        if ($name === '') {
            $name = $user->getEmail() ?: 'Użytkownik';
        }

        return new Reporter(
            $user->getUUID()->toString(),
            (string) ($user->getEmail() ?? ''),
            $name,
            (string) $tenant['uuid'],
            (string) $tenant['slug'],
            (string) $tenant['name'],
        );
    }
}
