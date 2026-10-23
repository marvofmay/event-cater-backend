<?php

declare(strict_types=1);

namespace App\Module\Event\Presentation\API;

use App\Common\Presentation\Http\PlanningPayload;
use App\Module\Event\Application\DTO\EventDetails;
use App\Module\Event\Domain\ValueObject\EventContact;
use App\Module\Event\Domain\ValueObject\EventLocation;
use App\Module\Event\Domain\ValueObject\EventSchedule;
use App\Module\Event\Domain\ValueObject\MenuItem;

final class EventPayloadMapper
{
    public static function details(PlanningPayload $body): EventDetails
    {
        $location = $body->object('location');
        return new EventDetails(
            $body->text('title'),
            new EventSchedule(
                $body->text('date'),
                $body->text('timezone', false) ?? 'Europe/Warsaw',
                $body->dateTime('readyAt'),
                $body->dateTime('startsAt'),
                $body->dateTime('endsAt'),
            ),
            new EventLocation($location->text('venue', false), $location->text('street', false), $location->text('city', false), $location->text('postalCode', false), $location->text('instructions', false)),
            $body->text('description', false),
            $body->integer('guestCount', false),
            $body->uuid('coordinatorUuid', false),
            $body->text('customerName', false)
        );
    }

    public static function contacts(PlanningPayload $body): array
    {
        return array_map(static function ($value): EventContact {
            $item = PlanningPayload::item($value);
            return new EventContact($item->text('name'), $item->text('phone'), $item->text('email', false), $item->text('function', false));
        }, $body->list('contacts', 50));
    }

    public static function menu(PlanningPayload $body): array
    {
        return array_map(static function ($value): MenuItem {
            $item = PlanningPayload::item($value);
            return new MenuItem($item->text('name'), $item->integer('portions'), $item->text('section', false), $item->text('portionSize', false), $item->text('servingStyle', false), $item->labels('diets'), $item->labels('allergens'), $item->text('notes', false));
        }, $body->list('items', 300));
    }
}
