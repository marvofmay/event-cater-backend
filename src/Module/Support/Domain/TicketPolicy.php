<?php

declare(strict_types=1);

namespace App\Module\Support\Domain;

use App\Common\Domain\Exception\BusinessRuleViolation;

/** Rules of the support ticket aggregate. Persistence stays behind a port. */
final class TicketPolicy
{
    public const int MAX_ATTACHMENTS = 5;
    public const int MAX_ATTACHMENT_BYTES = 8 * 1024 * 1024;
    public const int MAX_MESSAGE_LENGTH = 8000;
    public const int HOURLY_LIMIT = 10;

    public static function statusAfterUserReply(string $status): string
    {
        self::assertStatus($status);

        return in_array($status, [TicketStatus::Waiting->value, TicketStatus::Resolved->value, TicketStatus::Closed->value], true)
            ? TicketStatus::Open->value
            : $status;
    }

    public static function assertStatus(string $status): TicketStatus
    {
        return TicketStatus::tryFrom($status) ?? throw new BusinessRuleViolation('Wybierz status zgłoszenia.');
    }

    public static function assertCategory(string $category): Category
    {
        return Category::tryFrom($category) ?? throw new BusinessRuleViolation('Wybierz kategorię zgłoszenia.');
    }

    public static function subject(string $subject): string
    {
        $subject = self::plain($subject);
        $length = mb_strlen($subject);
        if ($length < 3 || $length > 160) {
            throw new BusinessRuleViolation('Temat jest wymagany i może mieć od 3 do 160 znaków.');
        }

        return $subject;
    }

    public static function message(string $message, bool $required): string
    {
        $message = self::plain($message);
        if ($required && $message === '') {
            throw new BusinessRuleViolation('Wpisz treść wiadomości.');
        }
        if (mb_strlen($message) > self::MAX_MESSAGE_LENGTH) {
            throw new BusinessRuleViolation('Wiadomość może mieć najwyżej 8000 znaków.');
        }

        return $message;
    }

    public static function assertAttachmentCount(int $count): void
    {
        if ($count > self::MAX_ATTACHMENTS) {
            throw new BusinessRuleViolation('Można dodać najwyżej 5 załączników.');
        }
    }

    private static function plain(string $value): string
    {
        $value = trim($value);
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) {
            throw new BusinessRuleViolation('Tekst zawiera niedozwolone znaki.');
        }

        return $value;
    }
}
