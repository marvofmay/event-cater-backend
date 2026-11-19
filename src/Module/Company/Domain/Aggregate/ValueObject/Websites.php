<?php

declare(strict_types=1);

namespace App\Module\Company\Domain\Aggregate\ValueObject;

use App\Common\Shared\Utils\WebsiteValidator;
use Symfony\Component\HttpFoundation\Response;

final class Websites
{
    public function __construct(private array $websites)
    {
        $normalized = [];
        foreach ($websites as $url) {
            if (!is_string($url) || trim($url) === '') {
                continue;
            }

            $url = trim($url);
            $error = WebsiteValidator::validate($url);
            if (null !== $error) {
                throw new \Exception($error, Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $normalized[] = $url;
        }

        $this->websites = $normalized;
    }

    public static function fromArray(array $websites): self
    {
        return new self($websites);
    }

    public function toArray(): array
    {
        return $this->websites;
    }
}
