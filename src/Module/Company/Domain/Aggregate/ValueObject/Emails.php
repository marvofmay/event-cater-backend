<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Aggregate\ValueObject;

use App\Common\Shared\Utils\EmailValidator;
use Symfony\Component\HttpFoundation\Response;

final class Emails
{
    public function __construct(private array $emails)
    {
        $normalized = [];
        foreach ($emails as $email) {
            if (!is_string($email) || trim($email) === '') {
                continue;
            }

            $email = trim($email);
            $error = EmailValidator::validate($email);
            if (null !== $error) {
                throw new \Exception($error, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $normalized[] = $email;
        }

        $this->emails = $normalized;
    }

    public static function fromArray(array $emails): self
    {
        return new self($emails);
    }

    public function toArray(): array
    {
        return $this->emails;
    }
}
