<?php

declare(strict_types=1);

namespace App\tests\integration\module\company\application\queryHandler;

use App\Module\Company\Application\DTO\Role\RolesQueryDTO;
use App\Module\Company\Application\Query\Role\ListRolesQuery;
use App\Module\Company\Application\QueryHandler\Role\ListRolesQueryHandler;
use App\tests\functional\FunctionalTestBase;

class ListRolesQueryHandlerTest extends FunctionalTestBase
{
    public function testInvokeReturnsListOfRoles(): void
    {
        $dto = new RolesQueryDTO();
        $query = new ListRolesQuery($dto);

        $handler = $this->getContainer()->get(ListRolesQueryHandler::class);
        $result = $handler->handle($query);

        $this->assertIsArray($result);
    }
}
