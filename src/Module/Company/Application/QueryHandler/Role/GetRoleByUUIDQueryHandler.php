<?php

declare(strict_types=1);

namespace App\Module\Company\Application\QueryHandler\Role;

use App\Common\Domain\Interface\QueryInterface;
use App\Module\Company\Application\Event\Role\RoleViewedEvent;
use App\Module\Company\Application\Query\Role\GetRoleByUUIDQuery;
use App\Module\Company\Application\Transformer\Role\RoleDataTransformer;
use App\Module\Company\Domain\Interface\Role\RoleReaderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler(bus: 'query.bus')]
final readonly class GetRoleByUUIDQueryHandler
{
    public function __construct(
        private RoleReaderInterface $roleReaderRepository,
        private EventDispatcherInterface $eventDispatcher,
        #[AutowireIterator(tag: 'app.role.query.get.validator')] protected iterable $validators,
    ) {
    }

    public function __invoke(GetRoleByUUIDQuery $query): array
    {
        $this->validate($query);

        $role = $this->roleReaderRepository->getRoleByUUID($query->roleUUID);
        $transformer = new RoleDataTransformer();
        $data = $transformer->transformToArray($role);
        $data['accesses'] = $transformer->transformAssignedAccesses($role);

        $this->eventDispatcher->dispatch(new RoleViewedEvent([GetRoleByUUIDQuery::ROLE_UUID => $query->roleUUID]));

        return $data;
    }

    private function validate(QueryInterface $query): void
    {
        foreach ($this->validators as $validator) {
            if (method_exists($validator, 'supports') && $validator->supports($query)) {
                $validator->validate($query);
            }
        }
    }
}
