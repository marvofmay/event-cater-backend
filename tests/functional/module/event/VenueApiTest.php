<?php

declare(strict_types=1);

namespace App\tests\functional\module\event;

use App\tests\functional\FunctionalTestBase;

final class VenueApiTest extends FunctionalTestBase
{
    private function api(string $method, string $path, array $body = [], int $status = 200): array
    {
        $this->client->jsonRequest($method, $path, $body);
        self::assertResponseStatusCodeSame($status, $this->client->getResponse()->getContent());
        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
    private function venue(): array
    {
        return $this->api('POST', '/api/event-venues', ['name' => 'Dom kultury', 'location' => ['venue' => 'Sala główna', 'street' => 'Długa 1', 'city' => 'Kraków', 'postalCode' => '30-001', 'instructions' => 'Rozładunek od podwórza; kuchnia na parterze'], 'contacts' => [['name' => 'Anna', 'phone' => '123456789']], 'notes' => 'Stały klient'], 201);
    }
    public function testRepeatedVisitsCopyIndependentSnapshotsAndSaveFromEvent(): void
    {
        $this->getAuthenticatedClient();
        $venue = $this->venue();
        $first = $this->api('POST', '/api/events', ['title' => 'Spotkanie świąteczne', 'date' => '2026-12-20'], 201);
        $first = $this->api('PUT', '/api/events/'.$first['uuid'].'/venue', ['version' => $first['version'], 'venueUuid' => $venue['uuid'], 'venueVersion' => $venue['version'], 'replaceContacts' => true]);
        self::assertSame('Długa 1', $first['location']['street']);
        self::assertSame('Anna', $first['contacts'][0]['name']);
        $venue['location']['street'] = 'Długa 2';
        $venue = $this->api('PUT', '/api/event-venues/'.$venue['uuid'], $venue);
        $first = $this->api('GET', '/api/events/'.$first['uuid']);
        self::assertSame('Długa 1', $first['location']['street']);
        $second = $this->api('POST', '/api/events', ['title' => 'Kolejne spotkanie', 'date' => '2027-01-10'], 201);
        $this->api('PUT', '/api/events/'.$second['uuid'].'/venue', ['version' => 1, 'venueUuid' => $venue['uuid'], 'venueVersion' => 1, 'replaceContacts' => true], 409);
        $second = $this->api('PUT', '/api/events/'.$second['uuid'].'/venue', ['version' => 1, 'venueUuid' => $venue['uuid'], 'venueVersion' => $venue['version'], 'replaceContacts' => false]);
        self::assertSame('Długa 2', $second['location']['street']);
        self::assertSame([], $second['contacts']);
        $saved = $this->api('POST', '/api/events/'.$first['uuid'].'/save-venue', ['version' => $first['version'], 'name' => 'Poprzednia sala'], 201);
        self::assertSame('Długa 1', $saved['location']['street']);
        self::assertSame('Anna', $saved['contacts'][0]['name']);
        $history = $this->api('GET', '/api/events/'.$first['uuid'].'/history');
        self::assertSame('venue_applied', $history['items'][0]['action']);
        $options = $this->api('GET', '/api/event-venues/options?q=Kraków');
        self::assertCount(2, $options['items']);
        self::assertArrayNotHasKey('contacts', $options['items'][0]);
    }
    public function testArchiveAndValidationAndOptimisticConcurrency(): void
    {
        $this->api('GET', '/api/event-venues', [], 401);
        $this->getAuthenticatedClient();
        $venue = $this->venue();
        $this->api('POST', '/api/event-venues', ['name' => 'Brak adresu', 'contacts' => [], 'location' => ['street' => '', 'city' => 'Kraków', 'postalCode' => '30-001']], 422);
        $venue = $this->api('PUT', '/api/event-venues/'.$venue['uuid'].'/active', ['version' => 1, 'active' => false]);
        self::assertSame(0, $this->api('GET', '/api/event-venues')['total']);
        self::assertSame(1, $this->api('GET', '/api/event-venues?active=all')['total']);
        $this->api('PUT', '/api/event-venues/'.$venue['uuid'].'/active', ['version' => 1, 'active' => true], 409);
        $event = $this->api('POST', '/api/events', ['title' => 'Przyjęcie', 'date' => '2026-12-31'], 201);
        $this->api('PUT', '/api/events/'.$event['uuid'].'/venue', ['version' => 1, 'venueUuid' => $venue['uuid'], 'venueVersion' => $venue['version'], 'replaceContacts' => true], 422);
        self::assertSame([], $this->api('GET', '/api/events/'.$event['uuid'])['contacts']);
        self::assertSame(1, $this->api('GET', '/api/events/'.$event['uuid'])['version']);
        $this->api('PUT', '/api/event-venues/'.$venue['uuid'].'/active', ['version' => $venue['version'], 'active' => true]);
        self::assertCount(1, $this->api('GET', '/api/event-venues/options?q=Długa')['items']);
        self::assertCount(0, $this->api('GET', '/api/event-venues/options?q=%')['items']);
    }
}
