<?php

declare(strict_types=1);

namespace App\tests\functional\module\company\presentation\api\controller;

use App\Module\Company\Domain\Entity\Department;
use App\Module\Company\Domain\Entity\Position;
use App\tests\functional\FunctionalTestBase;
use Ramsey\Uuid\Uuid;

final class PositionDepartmentSortingTest extends FunctionalTestBase
{
    public function testDepartmentSortingIsStableAcrossPagesAndIgnoresDeletedAssignments(): void
    {
        $this->getAuthenticatedClient();
        $manager = static::getContainer()->get('doctrine')->getManager();
        $departments = [];
        foreach (['Alfa', 'Beta', 'Zeta'] as $name) {
            $department = new Department();
            $department->setUUID(Uuid::uuid4()->toString());
            $department->setName($name);
            $department->setInternalCode($name);
            $department->setCompany(null);
            $department->setActive(true);
            $manager->persist($department);
            $departments[$name] = $department;
        }
        foreach (['Zebra' => ['Alfa'], 'Alpha' => ['Beta'], 'Multiple' => ['Beta', 'Alfa'], 'Empty' => [], 'Deleted' => ['Zeta']] as $name => $assigned) {
            $position = Position::create('SORT-TEST '.$name, null, true);
            foreach ($assigned as $department) {
                $position->addDepartment($departments[$department]);
            }
            if ($name === 'Deleted') {
                $position->getPositionDepartments()->first()->setDeletedAt(new \DateTime());
            }
            $manager->persist($position);
        }
        $manager->flush();
        $manager->clear();
        foreach (['asc' => ['Deleted', 'Empty', 'Multiple', 'Zebra', 'Alpha'], 'desc' => ['Alpha', 'Multiple', 'Zebra', 'Deleted', 'Empty']] as $direction => $expected) {
            $names = [];
            $uuids = [];
            for ($page = 1; $page <= 3; $page++) {
                $parameters = http_build_query(['phrase' => 'SORT-TEST', 'sortBy' => 'departments', 'sortDirection' => $direction, 'pageSize' => 2, 'page' => $page, 'includes' => 'positionDepartments']);
                $this->client->request('GET', '/api/positions?'.$parameters);
                self::assertResponseIsSuccessful($this->client->getResponse()->getContent());
                $data = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'];
                self::assertSame(5, $data['total']);
                foreach ($data['items'] as $item) {
                    $names[] = $item['name'];
                    $uuids[] = $item['uuid'];
                }
            }
            self::assertSame(array_map(static fn (string $name): string => 'SORT-TEST '.$name, $expected), $names);
            self::assertCount(5, array_unique($uuids));
        }
    }
}
