<?php

declare(strict_types=1);

namespace App\tests\functional\module\note;

use App\Module\Company\Domain\Entity\User;
use App\Module\Note\Domain\Entity\Note;
use App\Module\Note\Domain\Enum\NotePriorityEnum;
use App\tests\functional\FunctionalTestBase;

final class NoteSortingTest extends FunctionalTestBase
{
    private string $userUuid;

    private function fixtures(): void
    {
        $this->getAuthenticatedClient();
        $manager = self::getContainer()->get('doctrine')->getManager();
        self::assertStringContainsString('_test', $manager->getConnection()->getDatabase());
        $user = $manager->getRepository(User::class)->findOneBy(['email' => 'admin.ec@gmail.com']);
        $this->userUuid = $user->getUUID()->toString();
        foreach ([NotePriorityEnum::MEDIUM, NotePriorityEnum::LOW, NotePriorityEnum::HIGH] as $priority) {
            foreach (['A', 'B'] as $suffix) {
                $manager->persist(Note::create($user, 'PRIOTEST '.$priority->value.' '.$suffix, 'Treść', $priority));
            }
        }
        $manager->flush();
        $manager->getConnection()->executeStatement('UPDATE note SET created_at = ? WHERE title LIKE ?', ['2026-10-28 12:00:00', 'PRIOTEST%']);
    }

    private function listing(string $direction, int $page = 1, int $pageSize = 20): array
    {
        $this->client->request('GET', '/api/users/notes?'.http_build_query([
            'sortBy' => 'priority', 'sortDirection' => $direction, 'page' => $page, 'pageSize' => $pageSize, 'phrase' => 'PRIOTEST', 'userUUID' => $this->userUuid,
        ]));
        self::assertResponseIsSuccessful();

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['data'];
    }

    public function testPriorityAscendingAndDescendingUseImportanceNotAlphabeticalOrder(): void
    {
        $this->fixtures();
        foreach (['asc' => ['low', 'low', 'medium', 'medium', 'high', 'high'], 'desc' => ['high', 'high', 'medium', 'medium', 'low', 'low']] as $direction => $priorities) {
            $data = $this->listing($direction);
            self::assertSame(6, $data['total']);
            self::assertSame($priorities, array_column($data['items'], 'priority'), $direction);
            foreach (array_chunk($data['items'], 2) as $samePriority) {
                $ids = array_column($samePriority, 'uuid');
                $sorted = $ids;
                sort($sorted);
                self::assertSame($sorted, $ids);
            }
            $pages = array_merge($this->listing($direction, 1, 3)['items'], $this->listing($direction, 2, 3)['items']);
            self::assertSame(array_column($data['items'], 'uuid'), array_column($pages, 'uuid'));
            self::assertSame([], $this->listing($direction, 3, 3)['items']);
        }
    }
}
