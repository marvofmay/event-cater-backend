<?php

declare(strict_types=1);

namespace App\tests\unit\module\system\notification;

use App\Module\System\Notification\Domain\Catalog\NotificationDefaultContent;
use PHPUnit\Framework\TestCase;

final class NotificationAssignmentTemplatesTest extends TestCase
{
    public function testStaffTemplateShowsTheFunctionLabelInsteadOfHidingItInTheVariable(): void
    {
        $template = NotificationDefaultContent::forEvent('EventStaffAssigned');
        self::assertNotNull($template);
        self::assertSame(
            'Zostałeś przypisany do obsady wydarzenia „{{eventTitle}}” ({{eventDate}}). Twoje funkcje: {{functions}}.',
            $template['content'],
        );
        self::assertSame(['eventTitle', 'eventDate', 'functions'], NotificationDefaultContent::variablesFor('EventStaffAssigned'));

        $rendered = $this->render($template['content'], [
            'eventTitle' => 'Wesele Kowalskich',
            'eventDate' => '2026-12-01',
            'functions' => 'Kelner',
        ]);

        self::assertSame(
            'Zostałeś przypisany do obsady wydarzenia „Wesele Kowalskich” (2026-12-01). Twoje funkcje: Kelner.',
            $rendered,
        );
        self::assertSame(1, substr_count($rendered, 'Twoje funkcje:'));
    }

    public function testPackingTemplateDoesNotClaimThePersonIsOnTheEventStaff(): void
    {
        $template = NotificationDefaultContent::forEvent('EventPackerAssigned');
        self::assertNotNull($template);
        self::assertSame(
            'Odpowiadasz za skompletowanie kateringu na wydarzenie „{{eventTitle}}” ({{eventDate}}).',
            $template['content'],
        );
        self::assertSame(['eventTitle', 'eventDate'], NotificationDefaultContent::variablesFor('EventPackerAssigned'));
        self::assertStringNotContainsString('staffNote', $template['content']);

        $rendered = $this->render($template['content'], [
            'eventTitle' => 'Wesele Kowalskich',
            'eventDate' => '2026-12-01',
            'staffNote' => '',
        ]);

        self::assertSame(
            'Odpowiadasz za skompletowanie kateringu na wydarzenie „Wesele Kowalskich” (2026-12-01).',
            $rendered,
        );
        self::assertStringNotContainsString('obsadzie', $rendered);
    }

    /** @param array<string, string> $payload */
    private function render(string $template, array $payload): string
    {
        foreach ($payload as $key => $value) {
            $template = str_replace('{{'.$key.'}}', $value, $template);
        }

        return $template;
    }
}
